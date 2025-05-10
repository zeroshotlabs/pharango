<?php declare(strict_types=1);

namespace pharango;
use pharango\ClientException as client_exception;


class Collection implements \ArrayAccess, \Countable
{
    use _pharango;
    public bool $_auto_ddl;
    public string $_name;
    public string $_doc_name;
    public Connection $_connection;
    protected array $_edgeDefinitions = [];
    protected string $_graphName = 'hop_graph';

    // doc_name is the singular name of the collection by default
    public function __construct(Connection $connection, string $name = '',string $doc_name = '', bool $auto_ddl = true)
    {
        $this->_name = empty($name) ? get_class($this) : $name;
        $this->_connection = $connection;
        $this->_doc_name = empty($doc_name) ? rtrim($this->_name, 's') : $doc_name;
        $this->_auto_ddl = $auto_ddl;

        if( $this->_auto_ddl )
        {
            $this->ensure_exists();
            $this->ensure_edge_collections();
        }
    }

    public function __toString(): string
    {
        return $this->_name;
    }

    public function ensure_exists(): bool
    {
        // AQL to check if collection exists
        $result = $this->_connection->aql("FOR c IN COLLECTIONS() RETURN c.name");
        
        if (in_array($this->_name, $result))
            return true;

        // AQL to create collection
        $result = $this->create();

//        if (in_array($this->_name, $result))
            return true;

        // If the collection was not created, throw an exception
        throw new client_exception("Failed to create collection: " . $result['error']);
    }

    /**
     * Ensure edge collections exist
     */
    protected function ensure_edge_collections(): void
    {
        foreach ($this->_edgeDefinitions as $definition) {
            $edgeCollection = new Collection(
                $this->_connection,
                $definition['collection'],
                '',
                $this->_auto_ddl
            );
            $edgeCollection->ensure_exists();
        }
    }

    // ArrayAccess implementation - this is an insert()
    public function offsetSet($key, $value): void
    {
        if ($key === null)
        {
            $this->insert($value);
        } else
        {
            throw new client_exception("offsetSet: ".$key." specific key update/etc not implemented for collections");
        }
    }

    // $result = $this->_connection->make_request('POST', "document/{$this->_name}", $value);
    // $result = $this->_connection->make_request('PUT', "document/{$this->_name}/{$key}", $value);

    // supports array of constraints for find()
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
        if( $this->offsetGet($key) )
            return true;
        else
            return false;
    }

    public function offsetUnset($key): void
    {
        throw new client_exception("Unset not implemented - use delete() instead: ".$key);
    }

    /*
        Read documents from the collection
    */
    public function read(mixed $constraint = null): array
    {
        $constraint = $this->buildFilterFromConstraint($constraint);

        $result = $this->_connection->aql("FOR doc IN {$this->_name} {$constraint['filterClause']} RETURN doc", $constraint['bindVars']??[]);

        if (empty($result['result']))
            return [];

        return array_map(fn($doc) => new ($this->_doc_name)($this, $doc), $result['result']);
    }

    public function insert(array $data): Document
    {
        $queryData = $this->_connection->buildAqlQuery('insert', $data);
        
        $result = $this->_connection->aql($queryData['query'], $queryData['bindVars']);
        
        if (defined('_DEBUG'))
        {
            echo "\n\nINSERT QUERY: ".$queryData['query'];  
            echo "\n\nINSERT BIND VARS: ".json_encode($queryData['bindVars'],JSON_PRETTY_PRINT);
            echo "\n\nINSERT RESULT: ".json_encode($result,JSON_PRETTY_PRINT);
        }

        // if (!isset($result['result'][0]))
        //     throw new client_exception("Failed to insert document: " . json_encode($result));

        return new ($this->_doc_name)($this,$result);
    }

    public function find(array $example = [], int $limit = 0, int $skip = 0): Document|array|null
    {
        // Build the AQL query
        $query = "FOR doc IN " . $this->_name;
        
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

        if ($skip > 0)
            $query .= " SKIP @skip";

        // Add limit if specified
        if ($limit > 0)
            $query .= " LIMIT @limit";
        
        // Return the documents
        $query .= " RETURN doc";
        
        // Prepare bind variables
        $bindVars = $example;

//        $bindVars['@collection'] = $this->_name;
        
        if ($limit > 0)
            $bindVars['limit'] = (int) $limit;

        $result = $this->_connection->aql($query, $bindVars);

        if( defined('_DEBUG') )
        {
            error_log("QUERY: ".$query);  
            error_log("BIND VARS: ".json_encode($bindVars));
//            error_log("ERROR: ".print_r($result,true));
            error_log("RESULT/ERROR: ".print_r($result,true));
        }


        // $documents = [];
        // if (isset($result['result']) && $result['result'] instanceof \Traversable)
        //     foreach ($result['result'] as $doc)
        //         $documents[] = new Document($doc, $this);
        // else
        //     error_log("Result not an array: ".json_encode($result,JSON_PRETTY_PRINT));
        
        // Return based on number of results
        $documents = array_map(fn($doc) => new ($this->_doc_name)($this, $doc), $result);

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
        $query = "FOR doc IN {$this->_name} {$filter['filterClause']} REMOVE doc IN {$this->_name} RETURN OLD";

        $result = $this->_connection->aql($query, $filter['bindVars']);

        return count($result ?? []);
    }

    // Countable implementation
    public function count(): int
    {
        if (func_num_args() > 0)
        {
            // Example query: count($collection['name' => 'John'])
            $example = func_get_arg(0);

            // Build AQL query for counting with filter
            $query = "FOR doc IN @@collection";
            $bindVars = ['@collection' => $this->_name];
            
            if (!empty($example))
            {
                $query .= " FILTER ";
                $conditions = [];
                
                foreach ($example as $key => $value)
                {
                    $conditions[] = "doc.{$key} == @{$key}";
                    $bindVars[$key] = $value;
                }
                
                $query .= implode(" AND ", $conditions);
            }
            
            $query .= " COLLECT WITH COUNT INTO length RETURN length";

            $result = $this->_connection->aql($query, $bindVars);

            if (defined('_DEBUG'))
            {
                error_log("COUNT QUERY: ".$query);
                error_log("COUNT BIND VARS: ".json_encode($bindVars));
                error_log("COUNT RESULT: ".json_encode($result,JSON_PRETTY_PRINT));
            }

            return $result['result'][0] ?? 0;
        }
        
        // Count all documents using AQL
        $query = "RETURN LENGTH(@@collection)";
        $bindVars = ['@collection' => $this->_name];
        
        if (defined('_DEBUG'))
        {
            error_log("COUNT QUERY: ".$query);
            error_log("COUNT BIND VARS: ".json_encode($bindVars));
        }
        
        $result = $this->_connection->aql($query, $bindVars);
        
        if (defined('_DEBUG'))
            error_log("COUNT RESULT: ".json_encode($result,JSON_PRETTY_PRINT));
        
        return $result['result'][0] ?? 0;
    }

    /**
     * Build filter conditions for AQL queries from constraints
     * Returns the filter clause and bind variables
     */
    public function buildFilterFromConstraint(array|null $constraint): array
    {
        if (empty($constraint))
        {
            return [
                'filterClause' => '',
                'bindVars' => []
            ];
        }
        
        $bindVars = [];
        $conditions = [];

        foreach ($constraint as $field => $value)
        {
            $paramName = str_replace('.', '_', $field);
            
            if ($value === null)
            {
                $conditions[] = "doc.{$field} == null";
            }
            else
            {
                $conditions[] = "doc.{$field} == @{$paramName}";
                $bindVars[$paramName] = $value;
            }
        }
        
        return [
            'filterClause' => 'FILTER ' . implode(" && ", $conditions),
            'bindVars' => $bindVars
        ];
    }

    public function create(): array
    {
        try
        {
            $result = $this->_connection->make_request('POST', 'collection', ['name' => $this->_name]);

            if (isset($result['error']) && $result['error'])
                throw new client_exception("Failed to create collection: " . $result['errorMessage']);

            return $result;
        }
        catch (\Exception $e)
        {
            throw new client_exception("Failed to create collection: " . $e->getMessage());
        }
    }

    /**
     * Create a new document with validation
     */
    public function createDocument(array $data): Document
    {
        $doc = new ($this->_doc_name)($this, $data);
        $doc->validate();
        return $this->insert($doc->toArray());
    }

    /**
     * Update a document with validation
     */
    public function updateDocument(string $key, array $data): Document
    {
        $doc = new ($this->_doc_name)($this, $data);
        $doc->validate();
        $doc->_values['_key'] = $key;
        return $this->insert($doc->toArray());
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
    //     return $this->_name;
    // }

    // public function getConnection(): Connection
    // {
    //     return $this->_connection;
    // }



    /**
     * Check if the collection exists in the database
     */
    // public function exists(): bool
    // {
    //     try {
    //         $this->make_request('GET', "collection/{$this->_name}");
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
    //     $result = $this->make_request('GET', "document/{$this->_name}/{$key}");
    //     return new Document($result, $this);
    // }

    // /**
    //  * Find one document matching the example criteria
    //  */
    // public function readOneByExample(array $example): ?Document
    // {
    //     $result = $this->make_request('PUT', 'simple/query-by-example', [
    //         'collection' => $this->_name,
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
    //         'collection' => $this->_name,
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
    //         "document/{$this->_name}/{$key}?returnNew={$options['returnNew']}&keepNull={$options['keepNull']}", 
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
    //         "document/{$this->_name}/{$key}?returnNew={$options['returnNew']}", 
    //         $data
    //     );
        
    //     return new Document($result['new'] ?? $result, $this);
    // }

    // /**
    //  * Delete a document by key
    //  */
    // public function deleteDocument(string $key): bool
    // {
    //     $this->make_request('DELETE', "document/{$this->_name}/{$key}");
    //     return true;
    // }


