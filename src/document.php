<?php declare(strict_types=1);

namespace pharango;


// should be an ArrayObject and be mostly used as an object (collections are array)
class Document implements \ArrayAccess, \JsonSerializable
{
    public array $_values = [];
    public ?Collection $_collection = null;
    public ?Connection $_connection = null;
    public bool $_connected = true; // Default to connected mode when collection is set

    public function __construct(?array $data = null, ?Collection $collection = null)
    {
        $this->_collection = $collection;
        $this->_connection = $collection->_connection;

        if (is_array($data))
            $this->_values = $data;

        // If collection is provided, this document is in "connected" mode
        if ($collection !== null)
            $this->_connected = true;
    }

    public function toArray(): array
    {
        return $this->_values;
    }

    // /**
    //  * Set a value for a field
    //  * Updates the document in the database if connected to a collection
    //  */
    // public function set(string $key, $value): void
    // {
    //     if (in_array($key, ['_id', '_key', '_rev', '_from', '_to'])) {
    //         throw new \RuntimeException("Cannot set system field: {$key}");
    //     }
        
    //     $this->_values[$key] = $value;
        
    //     // Sync with database if connected
    //     if ($this->_connected && $this->_collection && isset($this->_values['_key'])) {
    //         $this->sync();
    //     }
    // }

    // /**
    //  * Get a value by key
    //  */
    // public function get(string $key): mixed
    // {
    //     return $this->_values[$key] ?? null;
    // }

    // /**
    //  * Check if a key exists
    //  */
    // public function has(string $key): bool
    // {
    //     return isset($this->_values[$key]);
    // }

    // /**
    //  * Remove a key from the document
    //  */
    // public function remove(string $key): void
    // {
    //     if (in_array($key, ['_id', '_key', '_rev', '_from', '_to']))
    //         throw new \RuntimeException("Cannot remove system field: {$key}");
        
    //     unset($this->_values[$key]);
        
    //     // Sync with database if connected
    //     if ($this->_connected && $this->_collection && isset($this->_values['_key']))
    //         $this->sync();
    // }

    // ArrayAccess implementation
    public function offsetSet($key, $value): void
    {
        if ($key === null) {
            throw new \RuntimeException('Cannot set value without a key');
        }
        
        // ArangoDB has reserved system fields that should not be modified directly
        if (in_array($key, ['_id', '_key', '_rev', '_from', '_to']))
            throw new \RuntimeException("Cannot set system field: {$key}");
        
        $this->_values[$key] = $value;
        
        // Save changes if we're in connected mode - use AQL query
        if ($this->_connected && $this->_collection && isset($this->_values['_key']))
        {

        }
    }

    public function offsetGet($key): mixed
    {
        return $this->_values[$key] ?? null;
    }

    public function offsetExists($key): bool
    {
        return isset($this->_values[$key]);
    }

    public function offsetUnset($key): void
    {
        if (in_array($key, ['_id', '_key', '_rev', '_from', '_to']))
            throw new \RuntimeException("Cannot remove system field: {$key}");
        
        unset($this->_values[$key]);
        
        // Save changes if we're in connected mode
        if ($this->_connected && $this->_collection && isset($this->_values['_key']))
            $this->sync();
    }

    // JsonSerializable implementation
    public function jsonSerialize(): array
    {
        return $this->_values;
    }

    /**
     * Get all document values
     */
    public function getAll(): array
    {
        return $this->_values;
    }

    /**
     * Sync document changes with the database
     * Used internally for connected mode
     */
    public function sync(): void
    {
        if (!$this->_collection)
            throw new \RuntimeException('Cannot sync document without a collection');
        
        
        if (isset($this->_values['_key']))
        {
            $queryData = $this->_connection->buildAqlQuery('update', $this->_values, [], $this->_collection->_collection_name);
            $result = $this->_connection->aql($queryData['query'],$queryData['bindVars']);

            // // Update existing document
            // // make update to the document using AQL query
            // $query = "UPDATE {$this->_collection}
            //             WITH {_key: '{$this->_values['_key']}'}
            //              SET @values";
            // // perform the AQL query same as the find() function
            // $result = $this->_connection->aql($query,['@values' =>['cpu_id'=>'fdsfsa']]);

            // Update revision
            if (isset($result['_rev']))
                $this->_values['_rev'] = $result['_rev'];
        }
        else
            throw new \RuntimeException('Cannot sync document without a key. Use save() for new documents.');
    }

    /**
     * Reload document data from the database
     */
    public function reload(): void
    {
        if (!$this->_collection)
            throw new \RuntimeException('Cannot reload document without a collection');
        
        if (!isset($this->_values['_key']))
            throw new \RuntimeException('Cannot reload document without a key');
        
        // make a get request to the document using AQL query
        $result = $this->_collection->find(['_key' => $this->_values['_key']]);

        $this->_values = $result;
    }

    /**
     * Set connection mode (connected or disconnected)
     */
    public function setConnected(bool $connected): void
    {
        $this->_connected = $connected;
    }

    /**
     * Get connection mode
     */
    public function isConnected(): bool
    {
        return $this->_connected;
    }

    /**
     * Set or get collection reference
     * If the parameter is null, returns the current collection
     * Otherwise, sets the collection and returns it
     */
    public function useCollection(?Collection $collection = null): ?Collection
    {
        if ($collection === null)
            return $this->_collection;

        $this->_collection = $collection;
        $this->_connected = true;

        return $this->_collection;
    }

    /**
     * Get collection reference
     */
    public function getCollection(): ?Collection
    {
        return $this->_collection;
    }
}


// class Document implements \ArrayAccess, \JsonSerializable, \ArrayObject
// {
//     protected array $_values = [];
//     protected ?Collection $_collection = null;

//     public function __construct(?array $data = null, ?Collection $collection = null)
//     {
//         $this->_collection = $collection;

//         if (is_array($data))
//             $this->_values = $data;
//     }

//     // https://docs.arangodb.com/3.11/aql/high-level-operations/
//     // set a key/value paid if there's a collection otherwise exception
//     public function set(string $key, $value): void
//     {
//         if ($this->_collection) {
//             $this->_values[$key] = $value;
//             // need a AQL query to set the value - use optimal functions like UPDATE
//             // fail with exception if the collection is not set
//             $this->_collection->make_request(
//                 'POST',
//                 "document/{$this->_collection})",
//                 $this->_values
//             );
//         } else {
//             throw new ClientException("Cannot set value without a collection for key: {$key}"); 
//         }
//     }

//     // ArrayAccess implementation
//     public function offsetSet($key, $value): void
//     {
//         if ($key === null) {
//             throw new \RuntimeException('Cannot set value without a key');
//         }
//         if (in_array($key, ['_id', '_key', '_rev'])) {
//             throw new ClientException("Cannot set system field: {$key}");
//         }
//         $this->_values[$key] = $value;
        
//         // Save changes if we have a collection
//         if ($this->_collection) {
//             if (isset($this->_values['_key'])) {
//                 // Update existing document
//                 $result = $this->_collection->make_request(
//                     'PATCH',
//                     "document/{$this->_collection->getCollectionName()}/{$this->_values['_key']}",
//                     ['document' => $this->_values]
//                 );
//                 $this->_values = $result;
//             }
//             else
//             {
//                 throw new Exception('DOCUMENT KEY NOT SET for "$key" => "$value"');
//                 // Create new document
//                 $result = $this->_collection->make_request(
//                     'POST',
//                     "document/{$this->_collection->getCollectionName()}",
//                     $this->_values
//                 );
//                 $this->_values = $result;
//             }
//         }
//     }

//     public function offsetGet($key): mixed
//     {
//         return $this->_values[$key] ?? null;
//     }

//     public function offsetExists($key): bool
//     {
//         return isset($this->_values[$key]);
//     }

//     public function offsetUnset($key): void
//     {
//         if (in_array($key, ['_id', '_key', '_rev'])) {
//             throw new \RuntimeException("Cannot remove system field: {$key}");
//         }
//         unset($this->_values[$key]);
        
//         // Save changes if we have a collection and document exists
//         if ($this->_collection && isset($this->_values['_key'])) {
//             $result = $this->_collection->make_request(
//                 'PATCH',
//                 "document/{$this->_collection->getCollectionName()}/{$this->_values['_key']}",
//                 ['document' => $this->_values]
//             );
//             $this->_values = $result;
//         }
//     }

//     // JsonSerializable implementation
//     public function jsonSerialize(): array
//     {
//         return $this->_values;
//     }

//     public function getAll(): array
//     {
//         return $this->_values;
//     }

//     public function save(): void
//     {
//         if (!$this->_collection) {
//             throw new \RuntimeException('Cannot save document without a collection');
//         }
        
//         if (isset($this->_values['_key'])) {
//             $this->_values = $this->_collection->make_request(
//                 'PATCH',
//                 "document/{$this->_collection->getCollectionName()}/{$this->_values['_key']}",
//                 ['document' => $this->_values]
//             );
//         } else {
//             $this->_values = $this->_collection->make_request(
//                 'POST',
//                 "document/{$this->_collection->getCollectionName()}",
//                 $this->_values
//             );
//         }
//     }

//     public function use_collection(?Collection $collection = null): Collection
//     {
//         if( $collection === null )
//             return $this->_collection;

//         $this->_collection = $collection;
//         return $this->_collection;
//     }
// }

