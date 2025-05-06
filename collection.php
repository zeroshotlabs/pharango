<?php
declare(strict_types=1);

class Collection
{
    protected string $_collectionName;
    protected Connection $_connection;

    public function __construct(string $collectionName, ?Connection $connection = null)
    {
        $this->_collectionName = $collectionName;
        $this->_connection = $connection ?? new Connection([
            'host' => 'localhost',
            'port' => 8529,
            'username' => 'root',
            'password' => '',
            'database' => '_system'
        ]);
    }

    public function exists(): bool
    {
        try {
            $this->_connection->make_request('GET', "collection/{$this->_collectionName}");
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function createCollection(): bool
    {
        if ($this->exists()) {
            return true;
        }

        try {
            $data = ['name' => $this->_collectionName];
            $this->_connection->make_request('POST', 'collection', $data);
            return true;
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to create collection: " . $e->getMessage());
        }
    }

    public function ensureCollection(): bool
    {
        if (!$this->exists()) {
            return $this->createCollection();
        }
        return true;
    }

    // Create
    public function create(array $data): Document
    {
        $this->ensureCollection();
        $document = new Document($data, $this);
        $document->save();
        return $document;
    }

    // Read
    public function get(string $key): Document
    {
        $result = $this->_connection->make_request('GET', "document/{$this->_collectionName}/{$key}");
        $document = new Document($result, $this);
        $document->setIsNew(false);
        return $document;
    }

    public function read(string $id): ?Document
    {
        try {
            return $this->get($id);
        } catch (\Exception $e) {
            if ($e->getMessage() === 'document not found') {
                return null;
            }
            throw $e;
        }
    }

    public function all(): array
    {
        return $this->readAll();
    }

    public function readAll(): array
    {
        return $this->readByExample([]);
    }

    public function readByExample(array $example, array $options = []): array
    {
        $path = "simple/by-example";
        $data = [
            'collection' => $this->_collectionName,
            'example' => $example
        ];
        
        if (!empty($options)) {
            $data = array_merge($data, $options);
        }
        
        $result = $this->_connection->make_request('PUT', $path, $data);
        
        $documents = [];
        foreach ($result['result'] ?? [] as $doc) {
            $document = new Document($doc, $this);
            $document->setIsNew(false);
            $documents[] = $document;
        }
        
        return $documents;
    }

    public function readOneByExample(array $example): ?Document
    {
        $result = $this->_connection->make_request('PUT', 'simple/by-example', [
            'collection' => $this->_collectionName,
            'example' => $example
        ]);
        
        return empty($result['result']) ? null : new Document($result['result'][0], $this);
    }

    // Update
    public function update(Document $document, array $data): Document
    {
        foreach ($data as $key => $value) {
            $document->set($key, $value);
        }
        return $this->replace($document);
    }

    // Delete
    public function delete(string $key): bool
    {
        $this->_connection->make_request('DELETE', "document/{$this->_collectionName}/{$key}");
        return true;
    }

    // Helper methods
    protected function save(Document $document): Document
    {
        if ($document->isNew()) {
            // Remove system fields for new documents
            $data = $document->getData();
            
            try {
                $result = $this->_connection->make_request('POST', "document/{$this->_collectionName}", $data);
                // Create new document with the response data
                $document = new Document($result, $this);
                $document->setIsNew(false);
            } catch (\Exception $e) {
                throw new \RuntimeException("Failed to create document: " . $e->getMessage());
            }
        } else {
            $result = $this->replace($document);
        }
        return $document;
    }

    protected function replace(Document $document): Document
    {
        $id = $document->getKey();
        if (!$id) {
            throw new \RuntimeException('Document must have a key to be replaced');
        }
        
        try {
            $result = $this->_connection->make_request('PUT', "document/{$this->_collectionName}/{$id}", $document->getData());
            $document->setRev($result['_rev']);
            return $document;
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to update document: " . $e->getMessage());
        }
    }

    public function store(Document $document): Document
    {
        if ($document->isNew()) {
            $document->set('_dateCreated', date('Y-m-d H:i:s'));
        }
        $document->set('_dateUpdated', date('Y-m-d H:i:s'));
        return $this->save($document);
    }

    public function getCollectionName(): string
    {
        return $this->_collectionName;
    }

    public function getConnection(): Connection
    {
        return $this->_connection;
    }

    public function count(array $example = []): int
    {
        if (empty($example)) {
            // Simple count of all documents
            $result = $this->_connection->make_request('GET', "collection/{$this->_collectionName}/count");
            return $result['count'] ?? 0;
        } else {
            // Count by example
            $result = $this->_connection->make_request('PUT', 'simple/count-by-example', [
                'collection' => $this->_collectionName,
                'example' => $example
            ]);
            return $result['count'] ?? 0;
        }
    }
}
