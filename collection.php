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
        $result = $this->_connection->make_request('POST', "document/{$this->_collectionName}", $data);
        return new Document($result, $this);
    }

    // Read
    public function get(string $key): Document
    {
        $result = $this->_connection->make_request('GET', "document/{$this->_collectionName}/{$key}");
        return new Document($result, $this);
    }

    public function readOneByExample(array $example): ?Document
    {
        $result = $this->_connection->make_request('PUT', 'simple/by-example', [
            'collection' => $this->_collectionName,
            'example' => $example,
            'limit' => 1
        ]);
        
        if (empty($result['result'])) {
            return null;
        }
        
        return new Document($result['result'][0], $this);
    }

    // Delete
    public function delete(string $key): bool
    {
        $this->_connection->make_request('DELETE', "document/{$this->_collectionName}/{$key}");
        return true;
    }

    public function getCollectionName(): string
    {
        return $this->_collectionName;
    }

    public function getConnection(): Connection
    {
        return $this->_connection;
    }
}
