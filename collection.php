<?php declare(strict_types=1);


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

    // Added method to get all documents in the collection
    public function getAll(int $limit = 100, int $skip = 0): Cursor
    {
        $query = [
            'query' => 'FOR doc IN @@collection LIMIT @skip, @limit RETURN doc',
            'bindVars' => [
                '@collection' => $this->_collectionName,
                'skip' => $skip,
                'limit' => $limit
            ]
        ];
        
        $result = $this->_connection->make_request('POST', 'cursor', $query);
        return new Cursor($this->_connection, $result, $this);
    }

    // Added method to get all document keys
    public function getKeys(int $limit = 100, int $skip = 0): array
    {
        $query = [
            'query' => 'FOR doc IN @@collection LIMIT @skip, @limit RETURN doc._key',
            'bindVars' => [
                '@collection' => $this->_collectionName,
                'skip' => $skip,
                'limit' => $limit
            ]
        ];
        
        $result = $this->_connection->make_request('POST', 'cursor', $query);
        return $result['result'] ?? [];
    }

    // Added method to get all document IDs
    public function getIds(int $limit = 100, int $skip = 0): array
    {
        $query = [
            'query' => 'FOR doc IN @@collection LIMIT @skip, @limit RETURN doc._id',
            'bindVars' => [
                '@collection' => $this->_collectionName,
                'skip' => $skip,
                'limit' => $limit
            ]
        ];
        
        $result = $this->_connection->make_request('POST', 'cursor', $query);
        return $result['result'] ?? [];
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

    // Added method to read multiple documents by example
    public function readByExample(array $example, int $limit = 100, int $skip = 0): Cursor
    {
        $result = $this->_connection->make_request('PUT', 'simple/by-example', [
            'collection' => $this->_collectionName,
            'example' => $example,
            'limit' => $limit,
            'skip' => $skip
        ]);
        
        return new Cursor($this->_connection, $result, $this);
    }

    // Update - added method to update document by key
    public function update(string $key, array $data, bool $returnNew = true): Document
    {
        $options = [
            'returnNew' => $returnNew,
            'keepNull' => false
        ];
        
        $result = $this->_connection->make_request(
            'PATCH', 
            "document/{$this->_collectionName}/{$key}?returnNew={$options['returnNew']}&keepNull={$options['keepNull']}", 
            $data
        );
        
        return new Document($result['new'] ?? $result, $this);
    }

    // Replace - added method to replace document by key
    public function replace(string $key, array $data, bool $returnNew = true): Document
    {
        $options = [
            'returnNew' => $returnNew
        ];
        
        $result = $this->_connection->make_request(
            'PUT', 
            "document/{$this->_collectionName}/{$key}?returnNew={$options['returnNew']}", 
            $data
        );
        
        return new Document($result['new'] ?? $result, $this);
    }

    // Delete
    public function delete(string $key): bool
    {
        $this->_connection->make_request('DELETE', "document/{$this->_collectionName}/{$key}");
        return true;
    }

    // Count documents in collection
    public function count(): int
    {
        $result = $this->_connection->make_request('GET', "collection/{$this->_collectionName}/count");
        return $result['count'] ?? 0;
    }

    // Truncate collection (remove all documents)
    public function truncate(): bool
    {
        $this->_connection->make_request('PUT', "collection/{$this->_collectionName}/truncate");
        return true;
    }

    // Execute AQL query
    public function query(string $query, array $bindVars = []): Cursor
    {
        $data = [
            'query' => $query,
            'bindVars' => $bindVars
        ];
        
        $result = $this->_connection->make_request('POST', 'cursor', $data);
        return new Cursor($this->_connection, $result, $this);
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



