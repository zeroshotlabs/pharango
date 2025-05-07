<?php declare(strict_types=1);

namespace pharango;


class Collection implements \ArrayAccess, \Countable
{
    use base_trait;
    public string $_collection_name;
    public Connection $_connection;

    public function __construct(string $collectionName, Connection $connection)
    {
        $this->_collection_name = $collectionName;
        $this->_connection = $connection;
    }

    public function __toString(): string
    {
        return $this->_collection_name;
    }

    // ArrayAccess implementation
    public function offsetSet($key, $value): void
    {
        $this->ensure_exists();
        
        if ($key === null) {
            // Create new document with auto-generated key
            $result = $this->make_request('POST', "document/{$this->_collection_name}", $value);
        } else {
            // Update or create document with specific key
            $result = $this->make_request('PUT', "document/{$this->_collection_name}/{$key}", $value);
        }
    }
    // FOR u IN users
    // FILTER u.active == true AND u.gender == "f"
    // SORT u.age ASC
    // LIMIT 5
    // RETURN u

    public function offsetGet($key): Document|null
    {
        if (is_array($key))
        {
            $doc = $this->find($key);
            if (empty($doc))
                return null;
            else
                return $doc[0];
        }

        return null;
    }

    public function offsetExists($key): bool
    {
        throw new \Exception("Not offsetExists implemented: ".$key);

        if (is_array($key)) {
            // Example query: isset($collection['name' => 'John'])
            $example = $key;
            $result = $this->make_request('PUT', 'simple/query-by-example', [
                'collection' => $this->_collection_name,
                'example' => $example,
                'limit' => 1
            ]);
            return !empty($result['result']);
        }
        
        // Check by key
        try {
            $this->make_request('GET', "document/{$this->_collection_name}/{$key}");
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function offsetUnset($key): void
    {
        throw new \Exception("Not unset implemented: ".$key);
        // if (is_array($key)) {
        //     // Example query: unset($collection['name' => 'John'])
        //     $example = $key;
        //     $result = $this->make_request('PUT', 'simple/query-by-example', [
        //         'collection' => $this->_collection_name,
        //         'example' => $example,
        //         'limit' => 1
        //     ]);
            
        //     if (!empty($result['result'])) {
        //         $key = $result['result'][0]['_key'];
        //         $this->make_request('DELETE', "document/{$this->_collection_name}/{$key}");
        //     }
        // } else {
        //     // Delete by key
        //     $this->make_request('DELETE', "document/{$this->_collection_name}/{$key}");
        // }
    }

    /**
     * Create the collection if it doesn't exist
     */
    public function ensure_exists(): bool
    {
        if ($this->exists())
            return true;

        try {
            $data = ['name' => $this->_collection_name];
            $this->make_request('POST', 'collection', $data);
            return true;
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to create collection: " . $e->getMessage());
        }
    }

    /**
     * Get information about the collection
     */
    public function read(mixed $constraint = null): array
    {
        $constraint = $this->buildFilterFromConstraint($constraint);

        $result = $this->make_request('POST', 'cursor', [
            'query' => "FOR doc IN {$this->_collection_name} {$constraint['filterClause']} RETURN doc",
            'bindVars' => $constraint['bindVars']??[]
        ]);

        return $result['result'] ?? [];
    }

    public function insert(array $data): Document
    {
        $queryData = $this->_connection->buildAqlQuery('insert', $data);
        
        if (defined('_DEBUG')) {
            echo "\n\nQUERY: ".$queryData['query'];  
            echo "\n\nBIND VARS: ".json_encode($queryData['bindVars']);
        }

        $result = $this->_connection->aql($queryData['query'], $queryData['bindVars']);
        
        if (defined('_DEBUG'))
            echo "\n\nRESULT: ".json_encode($result,JSON_PRETTY_PRINT);

        if (!isset($result['result'][0]))
            throw new \RuntimeException("Failed to insert document: " . json_encode($result));

        return new Document($result['result'][0], $this);
    }

    public function find(array $example = [], int $limit = 0): Document|array|null
    {
        // Build the AQL query
        $query = "FOR doc IN " . $this->_collection_name;
        
        // Add filter conditions for each key in the example
        if (!empty($example))
        {
            $query .= " FILTER ";
            $conditions = [];
            
            // Use proper parameter binding for all values
            foreach ($example as $key => $value)
                $conditions[] = "doc.{$key} == @{$key}";
            
            $query .= implode(" AND ", $conditions);
        }

        // Add limit if specified
        if ($limit > 0)
            $query .= " LIMIT @limit";
        
        // Return the documents
        $query .= " RETURN doc";
        
        // Prepare bind variables
        $bindVars = $example;

//        $bindVars['@collection'] = $this->_collection_name;
        
        if ($limit > 0)
            $bindVars['limit'] = (int) $limit;

        $result = $this->_connection->aql($query, $bindVars);

        if( defined('_DEBUG') )
        {
            echo "\n\nQUERY: ".$query;  
            echo "\n\nBIND VARS: ".json_encode($bindVars);
            echo "\n\nRESULT: ".json_encode($result,JSON_PRETTY_PRINT);
        }

        // error_log("RESULT: ".json_encode($result,JSON_PRETTY_PRINT));

        // $documents = [];
        // if (isset($result['result']) && $result['result'] instanceof \Traversable)
        //     foreach ($result['result'] as $doc)
        //         $documents[] = new Document($doc, $this);
        // else
        //     error_log("Result not an array: ".json_encode($result,JSON_PRETTY_PRINT));
        
        // Return based on number of results
        $documents = array_map(fn($doc) => new Document($doc, $this), $result);

        if (empty($documents))
            return [];
        else
            return $documents;
    }

    
    /**
     * Delete documents based on constraint (key or filter condition)
     * Returns the number of documents deleted
     */
    public function delete(string|array $constraint): int
    {
        // Filter-based deletion using AQL
        $filter = $this->buildFilterFromConstraint($constraint);
        $query = "FOR doc IN {$this->_collection_name} {$filter['filterClause']} REMOVE doc IN {$this->_collection_name} RETURN OLD";
        
        $result = $this->make_request('POST', 'cursor', [
            'query' => $query,
            'bindVars' => $filter['bindVars']
        ]);
        
        return count($result['result'] ?? []);
    }

    // Countable implementation
    public function count(): int
    {
        if (func_num_args() > 0) {
            // Example query: count($collection['name' => 'John'])
            $example = func_get_arg(0);
            
            // Build AQL query for counting with filter
            $query = "FOR doc IN @@collection";
            $bindVars = ['@collection' => $this->_collection_name];
            
            if (!empty($example)) {
                $query .= " FILTER ";
                $conditions = [];
                
                foreach ($example as $key => $value) {
                    $conditions[] = "doc.{$key} == @{$key}";
                    $bindVars[$key] = $value;
                }
                
                $query .= implode(" AND ", $conditions);
            }
            
            $query .= " COLLECT WITH COUNT INTO length RETURN length";
            
            if (defined('_DEBUG')) {
                var_dump($query);
                var_dump($bindVars);
            }
            
            $result = $this->_connection->aql($query, $bindVars);
            
            if (defined('_DEBUG')) {
                var_dump($result);
            }
            
            return $result['result'][0] ?? 0;
        }
        
        // Count all documents using AQL
        $query = "RETURN LENGTH(@@collection)";
        $bindVars = ['@collection' => $this->_collection_name];
        
        if (defined('_DEBUG')) {
            var_dump($query);
            var_dump($bindVars);
        }
        
        $result = $this->_connection->aql($query, $bindVars);
        
        if (defined('_DEBUG')) {
            var_dump($result);
        }
        
        return $result['result'][0] ?? 0;
    }


}


    /**
     * Execute AQL query
     */
    // public function query(string $query, array $bindVars = []): Cursor
    // {
    //     $data = [
    //         'query' => $query,
    //         'bindVars' => $bindVars
    //     ];
        
    //     $result = $this->make_request('POST', 'cursor', $data);
    //     return new Cursor($this->_connection, $result, $this);
    // }

    // public function make_request(string $method, string $url, array $data = []): array
    // {
    //     return $this->_connection->make_request($method, $url, $data);
    // }

    // public function getCollectionName(): string
    // {
    //     return $this->_collection_name;
    // }

    // public function getConnection(): Connection
    // {
    //     return $this->_connection;
    // }


    // /**
    //  * Build filter conditions for AQL queries from constraints
    //  * Returns the filter clause and bind variables
    //  */
    // protected function buildFilterFromConstraint(array|null $constraint): array
    // {
    //     if (empty($constraint)) {
    //         return [
    //             'filterClause' => '',
    //             'bindVars' => []
    //         ];
    //     }
        
    //     $bindVars = [];
    //     $conditions = [];

    //     foreach ($constraint as $field => $value) {
    //         $paramName = str_replace('.', '_', $field);
            
    //         if ($value === null) {
    //             $conditions[] = "doc.{$field} == null";
    //         } else {
    //             $conditions[] = "doc.{$field} == @{$paramName}";
    //             $bindVars[$paramName] = $value;
    //         }
    //     }
        
    //     return [
    //         'filterClause' => 'FILTER ' . implode(" && ", $conditions),
    //         'bindVars' => $bindVars
    //     ];
    // }

    /**
     * Check if the collection exists in the database
     */
    // public function exists(): bool
    // {
    //     try {
    //         $this->make_request('GET', "collection/{$this->_collection_name}");
    //         return true;
    //     } catch (\Exception $e) {
    //         return false;
    //     }
    // }

    // /**
    //  * Read a document by key
    //  */
    // public function readDocument(string $key): Document
    // {
    //     $result = $this->make_request('GET', "document/{$this->_collection_name}/{$key}");
    //     return new Document($result, $this);
    // }

    // /**
    //  * Find one document matching the example criteria
    //  */
    // public function readOneByExample(array $example): ?Document
    // {
    //     $result = $this->make_request('PUT', 'simple/query-by-example', [
    //         'collection' => $this->_collection_name,
    //         'example' => $example,
    //         'limit' => 1
    //     ]);
        
    //     if (empty($result['result'])) {
    //         return null;
    //     }
        
    //     return new Document($result['result'][0], $this);
    // }

    // /**
    //  * Find multiple documents matching the example criteria
    //  */
    // public function readByExample(array $example, int $limit = 100, int $skip = 0): Cursor
    // {
    //     $result = $this->make_request('PUT', 'simple/query-by-example', [
    //         'collection' => $this->_collection_name,
    //         'example' => $example,
    //         'limit' => $limit,
    //         'skip' => $skip
    //     ]);
        
    //     return new Cursor($this->_connection, $result, $this);
    // }

    // /**
    //  * Update a document by key
    //  */
    // public function updateDocument(string $key, array $data, bool $returnNew = true): Document
    // {
    //     $options = [
    //         'returnNew' => $returnNew,
    //         'keepNull' => false
    //     ];
        
    //     $result = $this->make_request(
    //         'PATCH', 
    //         "document/{$this->_collection_name}/{$key}?returnNew={$options['returnNew']}&keepNull={$options['keepNull']}", 
    //         $data
    //     );
        
    //     return new Document($result['new'] ?? $result, $this);
    // }

    // /**
    //  * Replace a document by key
    //  */
    // public function replaceDocument(string $key, array $data, bool $returnNew = true): Document
    // {
    //     $options = [
    //         'returnNew' => $returnNew
    //     ];
        
    //     $result = $this->make_request(
    //         'PUT', 
    //         "document/{$this->_collection_name}/{$key}?returnNew={$options['returnNew']}", 
    //         $data
    //     );
        
    //     return new Document($result['new'] ?? $result, $this);
    // }

    // /**
    //  * Delete a document by key
    //  */
    // public function deleteDocument(string $key): bool
    // {
    //     $this->make_request('DELETE', "document/{$this->_collection_name}/{$key}");
    //     return true;
    // }


