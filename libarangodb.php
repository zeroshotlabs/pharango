<?php declare(strict_types=1);

namespace arangodb;

use Exception as exception;
use JsonSerializable as json_serializable;
use ArrayAccess as array_access;



// Include the example AbstractEntity and AbstractCollection classes already provided
abstract class AbstractEntity extends Document implements \JsonSerializable
{
    /**
     * Collection name.
     *
     * @var string
     */
    protected $_collectionName;

    /**
     * Constructor.
     *
     * {@inheritdoc}
     *
     * @param array $options - optional, initial $options for document
     *
     * @throws \Exception
     */
    public function __construct( ?array $options = null)
    {
        parent::__construct($options);

        if (empty($this->_collectionName)) {
            throw new \Exception('No collection name provided!!!', 666);
        }
    }

    /**
     * @return string
     */
    public function getCollectionName()
    {
        return $this->_collectionName;
    }

    /**
     * Sets internal key (eg. when using in forms).
     *
     * @param string $key
     */
    public function setInternalKey($key)
    {
        parent::setInternalKey($key);
        if (empty($this->_id)) {
            $this->_id = $this->_collectionName . '/' . $key;
        }
    }

    /**
     * Called when entity is created
     */
    public function on_create()
    {

    }

    /**
     * Called when entity is saved
     */
    public function on_update()
    {

    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @link  http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     *        which is a value of any type other than a resource.
     * @since 3.2
     */
    // public function jsonSerialize()
    // {
    //     return $this->getAll();
    // }
}

abstract class AbstractCollection extends CollectionHandler
{

    /**
     * @var string collection name
     */
    protected $_collectionName;
    /**
     * @var DocumentHandler
     */
    protected $_documentHandler;
    /**
     * @var string
     */
    protected $_documentClass;

    /**
     * AbstractCollection constructor.
     *
     * {@inheritdoc}
     *
     * @param Connection $connection
     *
     * @throws \Exception
     */
    public function __construct(Connection $connection)
    {
        parent::__construct($connection);

        if (empty($this->_collectionName)) {
            throw new \Exception('No collection name provided!!!', 666);
        }

        $this->_documentHandler = new DocumentHandler($connection);
        if (!empty($this->_documentClass)) {
            $this->_documentHandler->setDocumentClass($this->_documentClass);
        }
    }

    /**
     * @return string
     */
    public function getCollectionNameString()
    {
        return $this->_collectionName;
    }

    /**
     * Get document(s) by specifying an example
     *
     * This will throw if the list cannot be fetched from the server
     *
     *
     * @throws Exception
     *
     * @param mixed      $document     - the example document as a Document object or an array
     * @param bool|array $options      - optional, prior to v1.0.0 this was a boolean value for sanitize, since v1.0.0 it's an array of options.
     *                                 <p>Options are :<br>
     *                                 <li>'_sanitize'         - True to remove _id and _rev attributes from result documents. Defaults to false.</li>
     *                                 <li>'sanitize'          - Deprecated, please use '_sanitize'.</li>
     *                                 <li>'_hiddenAttributes' - Set an array of hidden attributes for created documents.
     *                                 <li>'hiddenAttributes'  - Deprecated, please use '_hiddenAttributes'.</li>
     *                                 <p>
     *                                 This is actually the same as setting hidden attributes using setHiddenAttributes() on a document. <br>
     *                                 The difference is, that if you're returning a resultset of documents, the getAll() is already called <br>
     *                                 and the hidden attributes would not be applied to the attributes.<br>
     *                                 </p>
     *                                 </li>
     *                                 <li>'batchSize' - can optionally be used to tell the server to limit the number of results to be transferred in one batch</li>
     *                                 <li>'skip'      - Optional, The number of documents to skip in the query.</li>
     *                                 <li>'limit'     - Optional, The maximal amount of documents to return. 'skip' is applied before the limit restriction.</li>
     *                                 </p>
     *
     * @return cursor - Returns a cursor containing the result
     */
    public function findByExample($document, $options = [])
    {
        return parent::byExample($this->_collectionName, $document, $options);
    }

    /**
     * Find all documents for given keys
     *
     * @param array $ids - array of document keys
     *
     * @return array of matching entities
     */
    public function findByIds($ids)
    {
        return $this->lookupByKeys($this->_collectionName, $ids);
    }

    /**
     * Find by Example.
     *
     * @param array $example
     *
     * @return AbstractEntity|bool
     */
    public function findOneByExample($example)
    {
        $cursor = $this->byExample($this->_collectionName, $example);
        if ($cursor->getCount() > 0) {
            /* @var $document AbstractEntity */
            $document = $cursor->getAll()[0];
            $document->setIsNew(false);

            return $document;
        }

        return false;
    }

    /**
     * Gets one document by given ID
     *
     * @param string|int $id
     *
     * @return AbstractEntity|null
     * @throws ServerException
     */
    public function findOneById($id)
    {
        try {
            return $this->_documentHandler->getById($this->_collectionName, $id);
        } catch (ServerException $e) {
            if ($e->getServerMessage() === 'document not found') {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Gets internal collection name
     *
     * @return string
     */
    public function getInternalCollectionName()
    {
        return $this->_collectionName;
    }


    /**
     * Store a document to a collection
     *
     * {@inheritDoc}
     *
     * @param AbstractEntity $document
     *
     * @return mixed
     */
    public function store($document)
    {
        if (is_null($document->get('_dateCreated'))) {
            $document->set('_dateCreated', date('Y-m-d H:i:s'));
        }
        $document->set('_dateUpdated', date('Y-m-d H:i:s'));

        if ($document->getIsNew()) {
            if (method_exists($document, 'onCreate')) {
                $document->onCreate();
            }

            return $this->_documentHandler->save($this->_collectionName, $document);
        } else {
            if (method_exists($document, 'onUpdate')) {
                $document->onUpdate();
            }

            return $this->_documentHandler->replace($document);
        }
    }

    /**
     * Removes specified document from collection
     *
     * @param AbstractEntity $document
     * @param                $options
     *
     * @return array - an array containing an attribute 'removed' with the number of documents that were deleted, an an array 'ignored' with the number of not removed keys/documents
     */
    public function removeDocument(AbstractEntity $document, $options = [])
    {
        return $this->removeByKeys($this->_collectionName, [$document->getInternalKey()], $options);
    }
}


class Connection
{
    public string $host;
    public int $port;
    public string $username;
    public string $password;
    public string $database;

    public function __construct(array $options) {
        $this->host = $options['host'] ?? 'localhost';
        $this->port = $options['port'] ?? 8529;
        $this->username = $options['username'] ?? 'root';
        $this->password = $options['password'] ?? '';
        $this->database = $options['database'] ?? '_system';
    }

    public function use_db(string $database) {
        $this->database = $database;
    }

    protected function init_request(string $method, string $path, ?array $data = null, ?string $db = null): array {
        $db = $db ?? $this->database;
        $url = "http://{$this->host}:{$this->port}/_db/{$db}/_api/{$path}";
        
        $headers = [
            "Authorization: Basic " . base64_encode("{$this->username}:{$this->password}")
        ];
        
        if ($data !== null) {
            $headers[] = "Content-Type: application/json";
        }
        
        $options = [
            "http" => [
                "method" => $method,
                "header" => implode("\r\n", $headers),
                "ignore_errors" => true
            ]
        ];
        
        if ($data !== null) {
            $options["http"]["content"] = json_encode($data);
        }
        
        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        $result = json_decode($response, true);
        
        if (!empty($result['error'])) {
            throw new ServerException($result['errorMessage'], $result['code']);
        }
        
        return $result;
    }

    public function get_databases() {
        return $this->init_request('GET', 'database', null, '_system')['result'] ?? [];
    }
}

// namespace arangodb;

// $connectionOptions = [
//     "host" => "localhost",
//     "port" => 8529,
//     "username" => "root",
//     "password" => "zaun3r3",
//     "database" => "_system",
//     "timeout" => 30,
//     "connection" => "Keep-Alive",
//     "endpoint" => "tcp://localhost:8529",
//     "Authorization" => "Basic " . base64_encode("root:")
// ];
// ';
    


// // Example usage:
// /*
// // Initialize connection
// $connection = new Connection([
//     'host' => 'localhost',
//     'port' => 8529,
//     'username' => 'root',
//     'password' => '',
//     'database' => 'hoppr'
// ]);

class ArangoDocument implements \ArrayAccess
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

