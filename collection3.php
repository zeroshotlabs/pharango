<?php declare(strict_types=1);

namespace pharango;


class Collection
{
    protected string $_collection_name;
    protected Connection $_connection;

    public function __construct(string $collectionName, Connection $connection )
    {
        $this->_collection_name = $collectionName;
        $this->_connection = $connection;
    }

    /**
     * Build filter conditions for AQL queries from constraints
     * Returns the filter clause and bind variables
     */
    protected function buildFilterFromConstraint(array $constraint): array
    {
        if (empty($constraint)) {
            return [
                'filterClause' => '',
                'bindVars' => []
            ];
        }
        
        $bindVars = [];
        $conditions = [];
        
        foreach ($constraint as $field => $value) {
            $paramName = str_replace('.', '_', $field);
            
            if ($value === null)
                $conditions[] = "doc.{$field} == null";
            else
                $conditions[] = "doc.{$field} == @{$paramName}";
                $bindVars[$paramName] = $value;
        }
        
        return [
            'filterClause' => 'FILTER ' . implode(" && ", $conditions),
            'bindVars' => $bindVars
        ];
    }

    /**
     * Check if the collection exists in the database
     */
    public function exists(): bool
    {
        try {
            $this->make_request('GET', "collection/{$this->_collection_name}");
            return true;
        } catch (\Exception $e) {
            return false;
        }
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
     * Create a document in the collection
     */
    public function insert(array $data): Document
    {
        $result = $this->make_request('POST', "document/{$this->_collection_name}", $data);
        return new Document($result, $this);
    }

    /**
     * Get information about the collection
     */
    public function read( mixed $constraint = null ): array
    {
        $constraint = $this->buildFilterFromConstraint($constraint);

        $result = $this->make_request('POST', 'cursor', [
            'query' => "FOR doc IN {$this->_collection_name} {$constraint['filterClause']} RETURN doc",
            'bindVars' => $constraint['bindVars']
        ]);

        return $result['result'] ?? [];
    }

    /**
     * Read a document by key
     */
    public function readDocument(string $key): Document
    {
        $result = $this->make_request('GET', "document/{$this->_collection_name}/{$key}");
        return new Document($result, $this);
    }

    /**
     * Find one document matching the example criteria
     */
    public function readOneByExample(array $example): ?Document
    {
        $result = $this->make_request('PUT', 'simple/by-example', [
            'collection' => $this->_collection_name,
            'example' => $example,
            'limit' => 1
        ]);
        
        if (empty($result['result'])) {
            return null;
        }
        
        return new Document($result['result'][0], $this);
    }

    /**
     * Find multiple documents matching the example criteria
     */
    public function readByExample(array $example, int $limit = 100, int $skip = 0): Cursor
    {
        $result = $this->make_request('PUT', 'simple/by-example', [
            'collection' => $this->_collection_name,
            'example' => $example,
            'limit' => $limit,
            'skip' => $skip
        ]);
        
        return new Cursor($this->_connection, $result, $this);
    }

    /**
     * Update a document by key
     */
    public function updateDocument(string $key, array $data, bool $returnNew = true): Document
    {
        $options = [
            'returnNew' => $returnNew,
            'keepNull' => false
        ];
        
        $result = $this->make_request(
            'PATCH', 
            "document/{$this->_collection_name}/{$key}?returnNew={$options['returnNew']}&keepNull={$options['keepNull']}", 
            $data
        );
        
        return new Document($result['new'] ?? $result, $this);
    }

    /**
     * Replace a document by key
     */
    public function replaceDocument(string $key, array $data, bool $returnNew = true): Document
    {
        $options = [
            'returnNew' => $returnNew
        ];
        
        $result = $this->make_request(
            'PUT', 
            "document/{$this->_collection_name}/{$key}?returnNew={$options['returnNew']}", 
            $data
        );
        
        return new Document($result['new'] ?? $result, $this);
    }

    /**
     * Delete a document by key
     */
    public function deleteDocument(string $key): bool
    {
        $this->make_request('DELETE', "document/{$this->_collection_name}/{$key}");
        return true;
    }

    /**
     * Delete documents based on constraint (key or filter condition)
     * Returns the number of documents deleted
     */
    public function delete(string|array $constraint): int
    {
        if (is_string($constraint)) {
            // Simple key-based deletion
            try {
                $this->make_request('DELETE', "document/{$this->_collection_name}/{$constraint}");
                return 1;
            } catch (\Exception $e) {
                return 0;
            }
        } else {
            // Filter-based deletion using AQL
            $filter = $this->buildFilterFromConstraint($constraint);
            $query = "FOR doc IN {$this->_collection_name} {$filter['filterClause']} REMOVE doc IN {$this->_collection_name} RETURN OLD";
            
            $result = $this->make_request('POST', 'cursor', [
                'query' => $query,
                'bindVars' => $filter['bindVars']
            ]);
            
            return count($result['result'] ?? []);
        }
    }

    /**
     * Count documents in collection
     * Optionally filter by constraint
     */
    public function count(?array $constraint = null): int
    {
        if( empty($constraint) )
            $filter = '';
        else
            $filter = $this->buildFilterFromConstraint($constraint)['filterClause'];

            // if ($constraint === null) {
            //     // Simple count of all documents
            //     $result = $this->make_request('GET', "collection/{$this->_collection_name}/count");
            //     return $result['count'] ?? 0;
            // } else {
            // Filtered count using AQL
            $query = "RETURN LENGTH(FOR doc IN {$this->_collection_name} {$filter} RETURN doc)";
            
            $result = $this->make_request('POST', 'cursor', [
                'query' => $query,
                'bindVars' => $filter['bindVars']
            ]);
            
        return (int)($result['result'][0] ?? 0);
    }

    /**
     * Truncate collection (remove all documents)
     */
    public function truncate(): bool
    {
        // $this->make_request('PUT', "collection/{$this->_collection_name}/truncate");
        return true;
    }

    /**
     * Execute AQL query
     */
    public function query(string $query, array $bindVars = []): Cursor
    {
        $data = [
            'query' => $query,
            'bindVars' => $bindVars
        ];
        
        $result = $this->make_request('POST', 'cursor', $data);
        return new Cursor($this->_connection, $result, $this);
    }

    public function make_request(string $method, string $url, array $data = []): array
    {
        return $this->_connection->make_request($method, $url, $data);
    }
}




    // /**
    //  * Get all documents in the collection
    //  */
    // public function readAll(int $limit = 100, int $skip = 0): Cursor
    // {
    //     $query = [
    //         'query' => 'FOR doc IN @@collection LIMIT @skip, @limit RETURN doc',
    //         'bindVars' => [
    //             '@collection' => $this->_collection_name,
    //             'skip' => $skip,
    //             'limit' => $limit
    //         ]
    //     ];
        
    //     $result = $this->make_request('POST', 'cursor', $query);
    //     return new Cursor($this->_connection, $result, $this);
    // }

    // /**
    //  * Get all document keys in the collection
    //  */
    // public function getKeys(int $limit = 100, int $skip = 0): array
    // {
    //     $query = [
    //         'query' => 'FOR doc IN @@collection LIMIT @skip, @limit RETURN doc._key',
    //         'bindVars' => [
    //             '@collection' => $this->_collection_name,
    //             'skip' => $skip,
    //             'limit' => $limit
    //         ]
    //     ];
        
    //     $result = $this->make_request('POST', 'cursor', $query);
    //     return $result['result'] ?? [];
    // }

    // /**
    //  * Get all document IDs in the collection
    //  */
    // public function getIds(int $limit = 100, int $skip = 0): array
    // {
    //     $query = [
    //         'query' => 'FOR doc IN @@collection LIMIT @skip, @limit RETURN doc._id',
    //         'bindVars' => [
    //             '@collection' => $this->_collection_name,
    //             'skip' => $skip,
    //             'limit' => $limit
    //         ]
    //     ];
        
    //     $result = $this->make_request('POST', 'cursor', $query);
    //     return $result['result'] ?? [];
    // }



// class Collection implements \Stringable, \ArrayAccess
// {
//     protected bool $auto_create = true;
//     protected string $_collection_name;
//     protected Connection $_connection;

//     public function __construct(string $collection_name, Connection $connection )
//     {
//         $this->_collection_name = $collection_name;
//         $this->_connection = $connection;
//     }

//     public function __toString(): string
//     {
//         return $this->_collection_name;
//     }   

//     public function exists(): bool
//     {
//         try {
//             $this->make_request('GET', "collection/{$this->_collection_name}");
//             return true;
//         } catch (\Exception $e) {
//             return false;
//         }
//     }

//     // implement ArrayAccess
//     public function offsetExists(mixed $offset): bool
//     {
//         return $this->exists();
//     }

//     public function offsetGet(mixed $offset): mixed
//     {
//         return $this->get($offset);
//     }

//     public function offsetSet(mixed $offset, mixed $value): void
//     {
//         $this->set($offset, $value);
//     }

//     public function offsetUnset(mixed $offset): void
//     {
//         $this->delete($offset);
//     }


//     // Added method to read multiple documents by example
//     public function readByExample(array $example, int $limit = 100, int $skip = 0): Cursor
//     {
//         $result = $this->make_request('PUT', 'simple/by-example', [
//             'collection' => $this->_collection_name,
//             'example' => $example,
//             'limit' => $limit,
//             'skip' => $skip
//         ]);
        
//         return new Cursor($this->_connection, $result, $this);
//     }

//     // Update - added method to update document by key
//     public function update(string $key, array $data, bool $returnNew = true): Document
//     {
//         $options = [
//             'returnNew' => $returnNew,
//             'keepNull' => false
//         ];
        
//         $result = $this->make_request(
//             'PATCH', 
//             "document/{$this->_collection_name}/{$key}?returnNew={$options['returnNew']}&keepNull={$options['keepNull']}", 
//             $data
//         );
        
//         return new Document($result['new'] ?? $result, $this);
//     }

//     // Replace - added method to replace document by key
//     public function replace(string $key, array $data, bool $returnNew = true): Document
//     {
//         $options = [
//             'returnNew' => $returnNew
//         ];

//         $result = $this->make_request(
//             'PUT', 
//             "document/{$this->_collection_name}/{$key}?returnNew={$options['returnNew']}", 
//             $data
//         );
        
//         return new Document($result['new'] ?? $result, $this);
//     }

//     /**
//      * Delete documents based on constraint (key or filter condition)
//      * Returns the number of documents deleted
//      */
//     public function delete(string|array $constraint): int
//     {
//         if (is_string($constraint)) {
//             // Simple key-based deletion
//             try {
//                 $this->make_request('DELETE', "document/{$this->_collection_name}/{$constraint}");
//                 return 1;
//             } catch (\Exception $e) {
//                 return 0;
//             }
//         } else {
//             // Filter-based deletion using AQL
//             $query = "FOR doc IN {$this->_collection_name} ";
            
//             // Build filter conditions
//             if (!empty($constraint)) {
//                 $query .= "FILTER ";
//                 $bindVars = [];
//                 $conditions = [];
                
//                 foreach ($constraint as $field => $value) {
//                     $paramName = str_replace('.', '_', $field);
//                     $conditions[] = "doc.{$field} == @{$paramName}";
//                     $bindVars[$paramName] = $value;
//                 }
                
//                 $query .= implode(" && ", $conditions);
//             }
            
//             // Execute delete query
//             $query .= " REMOVE doc IN {$this->_collection_name} RETURN OLD";
            
//             $result = $this->make_request('POST', 'cursor', [
//                 'query' => $query,
//                 'bindVars' => $bindVars ?? []
//             ]);
            
//             return count($result['result'] ?? []);
//         }
//     }


//     // Count documents in collection
//     public function count(): int
//     {
//         $result = $this->make_request('GET', "collection/{$this->_collection_name}/count");
//         return $result['count'] ?? 0;
//     }

//     // Truncate collection (remove all documents)
//     // public function truncate(): bool
//     // {
//     //     $this->make_request('PUT', "collection/{$this->_collection_name}/truncate");
//     //     return true;
//     // }

//     // Execute AQL query
//     public function query(string $query, array $bindVars = []): Cursor
//     {
//         $data = [
//             'query' => $query,
//             'bindVars' => $bindVars
//         ];
        
//         $result = $this->make_request('POST', 'cursor', $data);
//         return new Cursor($this->_connection, $result, $this);
//     }

//     public function getcollection_name(): string
//     {
//         return $this->_collection_name;
//     }

//     public function getConnection(): Connection
//     {
//         return $this->_connection;
//     }
// }


    // // Added method to get all documents in the collection
    // public function getAll(int $limit = 100, int $skip = 0): Cursor
    // {
    //     $query = [
    //         'query' => 'FOR doc IN @@collection LIMIT @skip, @limit RETURN doc',
    //         'bindVars' => [
    //             '@collection' => $this->_collection_name,
    //             'skip' => $skip,
    //             'limit' => $limit
    //         ]
    //     ];
        
    //     $result = $this->make_request('POST', 'cursor', $query);
    //     return new Cursor($this->_connection, $result, $this);
    // }

    // // Added method to get all document keys
    // public function getKeys(int $limit = 100, int $skip = 0): array
    // {
    //     $query = [
    //         'query' => 'FOR doc IN @@collection LIMIT @skip, @limit RETURN doc._key',
    //         'bindVars' => [
    //             '@collection' => $this->_collection_name,
    //             'skip' => $skip,
    //             'limit' => $limit
    //         ]
    //     ];
        
    //     $result = $this->make_request('POST', 'cursor', $query);
    //     return $result['result'] ?? [];
    // }

    // // Added method to get all document IDs
    // public function getIds(int $limit = 100, int $skip = 0): array
    // {
    //     $query = [
    //         'query' => 'FOR doc IN @@collection LIMIT @skip, @limit RETURN doc._id',
    //         'bindVars' => [
    //             '@collection' => $this->_collection_name,
    //             'skip' => $skip,
    //             'limit' => $limit
    //         ]
    //     ];
        
    //     $result = $this->make_request('POST', 'cursor', $query);
    //     return $result['result'] ?? [];
    // }

    // public function readOneByExample(array $example): ?Document
    // {
    //     $result = $this->make_request('PUT', 'simple/by-example', [
    //         'collection' => $this->_collection_name,
    //         'example' => $example,
    //         'limit' => 1
    //     ]);
        
    //     if (empty($result['result'])) {
    //         return null;
    //     }
        
    //     return new Document($result['result'][0], $this);
    // }

