<?php declare(strict_types=1);

use JsonSerializable as json_serializable;
use ArrayAccess as array_access;


/*
this should be a document that is associated with a 
collection
*/



class DocumentHandler extends Handler
{
    
    protected string $documentClass = 'Document';

    public function __construct($connection)
    {
        parent::__construct($connection);
    }

    public function create($collectionName, array $data)
    {
        $document = $this->createDocument($data);
        return $this->save($collectionName, $document);
    }

    public function getById($collectionName, $id) {
        // If ID already contains collection name, extract just the key
        if (strpos($id, '/') !== false) {
            $parts = explode('/', $id);
            $id = end($parts);
        }

        try {
            $result = $this->make_request('GET', "document/{$collectionName}/{$id}");
            return $this->createDocument($result);
        } catch (exception $e) {
            if ($e->getMessage() === 'document not found') {
                return null;
            }
            throw $e;
        }
    }

    public function findByExample($collectionName, array $example, array $options = []) {
        $path = "simple/by-example";
        $data = [
            'collection' => $collectionName,
            'example' => $example
        ];
        
        if (!empty($options)) {
            $data = array_merge($data, $options);
        }
        
        $result = $this->make_request('PUT', $path, $data);
        
        $documents = [];
        foreach ($result['result'] ?? [] as $doc) {
            $documents[] = $this->createDocument($doc);
        }
        
        return $documents;
    }

    public function save($collectionName, $document) {
        $result = $this->make_request('POST', "document/{$collectionName}", $document->getAll());
        return $this->createDocument($result);
    }

    public function update($document, array $data) {
        foreach ($data as $key => $value) {
            $document->set($key, $value);
        }
        return $this->replace($document);
    }

    public function replace($document) {
        $id = $this->getDocumentKey($document);
        $collectionName = $this->getCollectionName($document);
        
        $result = $this->make_request('PUT', "document/{$collectionName}/{$id}", $document->getAll());
        $document->setRev($result['_rev']);
        return $document;
    }

    public function remove($document) {
        $id = $this->getDocumentKey($document);
        $collectionName = $this->getCollectionName($document);
        return $this->make_request('DELETE', "document/{$collectionName}/{$id}");
    }
}



class Document implements json_serializable,array_access
{
    protected $_id;
    protected $_key;
    protected $_rev;
    protected $_isNew = true;
    protected $_values = [];

    public function __construct( ?array $options = null) {
        if (is_array($options)) {
            foreach ($options as $key => $value) {
                $this->set($key, $value);
            }
        }
    }

    public function setInternalKey($key) {
        $this->_key = $key;
    }

    public function getInternalKey() {
        return $this->_key;
    }

    public function getId() {
        return $this->_id;
    }

    public function setId($id) {
        $this->_id = $id;
        $parts = explode('/', $id);
        if (count($parts) > 1) {
            $this->_key = $parts[1];
        }
    }

    public function getRev() {
        return $this->_rev;
    }

    public function setRev($rev) {
        $this->_rev = $rev;
    }

    public function getIsNew() {
        return $this->_isNew;
    }

    public function setIsNew($isNew) {
        $this->_isNew = $isNew;
    }

    public function offsetExists($key): bool {
        return isset($this->_values[$key]);
    }

    public function offsetGet($key): mixed {
        return $this->get($key);
    }

    public function offsetSet($key, $value): void {
        $this->set($key, $value);
    }

    public function offsetUnset($key): void {
        unset($this->_values[$key]);
    }

    public function set($key, $value): void {
        $this->_values[$key] = $value;
    }

    public function get($key): mixed {
        return $this->_values[$key] ?? null;
    }

    public function getAll(): array {
        $data = $this->_values;
        if ($this->_id) $data['_id'] = $this->_id;
        if ($this->_key) $data['_key'] = $this->_key;
        if ($this->_rev) $data['_rev'] = $this->_rev;
        return $data;
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize( ?array $options = null): array {
        return $this->getAll();
    }
}



class ddocument implements \ArrayAccess
{
    use ArangoRealtimePersistenceTrait;

    protected array $fields = [];
    protected $collectionName;
    protected $dbHandler; // Should be an instance of your DocumentHandler or similar
    protected $key; // _key

    public function __construct($collectionName, $dbHandler, array $data = [], bool $realtime = true)
    {
        $this->collectionName = $collectionName;
        $this->dbHandler = $dbHandler;
        $this->fields = $data;
        $this->realtimePersistence = $realtime;
        $this->key = $data['_key'] ?? null;

        // Set up the callback for persistence
        $this->setPersistCallback(function($field, $value, $allFields) {
            if ($this->key) {
                // Update only the changed field in the DB
                $this->update($this->collectionName, $this->key, [$field => $value]);
            } else {
                // Insert new document
                $result = $this->insert($this->collectionName, $allFields);
                $this->key = is_object($result) ? $result->_key : ($result['_key'] ?? null);
            }
        });
    }

    // ArrayAccess methods
    public function offsetExists($offset): bool
    {
        return isset($this->fields[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return $this->fields[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        $this->fields[$offset] = $value;
        $this->onFieldChange($offset, $value);
    }

    public function offsetUnset($offset): void
    {
        unset($this->fields[$offset]);
        $this->onFieldChange($offset, null);
    }

    // Manual save (for batch mode)
    public function save()
    {
        if ($this->key) {
            $this->update($this->collectionName, $this->key, $this->fields);
        } else {
            $result = $this->insert($this->collectionName, $this->fields);
            $this->key = is_object($result) ? $result->_key : ($result['_key'] ?? null);
        }
    }

    // Insert a new document
    public function insert($collectionName, $data)
    {
        $result = $this->dbHandler->create($collectionName, $data);
        if (isset($result->_key)) {
            $this->key = $result->_key;
        } elseif (is_array($result) && isset($result['_key'])) {
            $this->key = $result['_key'];
        }
        return $result;
    }

    // Update an existing document
    public function update($collectionName, $key, $data)
    {
        $doc = array_merge($this->fields, ['_key' => $key], $data);
        $result = $this->dbHandler->replace((object)$doc);
        if (is_object($result) && isset($result->_rev)) {
            $this->fields['_rev'] = $result->_rev;
        } elseif (is_array($result) && isset($result['_rev'])) {
            $this->fields['_rev'] = $result['_rev'];
        }
        return $result;
    }

    // Get all fields
    public function toArray(): array
    {
        return $this->fields;
    }
}


