<?php declare(strict_types=1);

namespace pharango;

use RuntimeException;
use Traversable;
// should be an ArrayObject and be mostly used as an object (collections are array)
class Document extends \ArrayObject implements \JsonSerializable
{
    public bool $_connected = false;
    public ?Collection $_collection;
    public ?Connection $_connection;

    public array $_struct = [];
    public array $_values = [];
    public array $values = [];
    protected string $_graphName = '';
    protected array $_edgeDefinitions = [];

    public function __construct( ?Collection $collection = null,array|Traversable|null $data = null )
    {
        if($collection)
        {
            $this->_collection = $collection;
            $this->_connection = $collection->_connection;
            $this->_connected = true;
        }

        // array_walk data elements into the struct
        // only use collection->struct keys to walk the data
        if (is_array($data) || $data instanceof \Traversable)
            // only use collection->struct keys to walk the data    
            foreach($this->_struct as $key => $value)
                $this->_values[$key] = $data[$key]??$value;
        else if( !empty($data) )
            throw new RuntimeException('Cannot walk data without an array or Traversable: hh'.print_r($data,true));

        // If collection is provided, this document is in "connected" mode
        if ($collection !== null)
            $this->use_collection($collection);
    }

    /**
     * Set or get collection reference
     * If the parameter is null, returns the current collection
     * Otherwise, sets the collection and returns it
     */
    public function use_collection(?Collection $collection = null): ?Collection
    {
        if ($collection === null)
            return $this->_collection;

        $this->_collection = $collection;
        $this->_connection = $collection->_connection;
        
        $this->_connected = true;

        return $this->_collection;
    }

    /**
     * Get the graph instance for this document
     */
    protected function getGraph(): Graph
    {
        return new Graph($this->_connection, $this->_graphName, $this->_edgeDefinitions);
    }

    /**
     * Create a relationship to another document
     */
    public function createRelationship(string $toKey, string $type, array $metadata = []): array
    {
        if (!isset($this->_values['_key'])) {
            throw new RuntimeException('Cannot create relationship without document key');
        }
        return $this->getGraph()->create_relationship($this->_values['_key'], $toKey, $type, $metadata);
    }

    /**
     * Get relationships from this document
     */
    public function getRelationships(?string $type = null, string $direction = 'OUTBOUND'): array
    {
        if (!isset($this->_values['_key'])) {
            throw new RuntimeException('Cannot get relationships without document key');
        }
        return $this->getGraph()->get_relationships($this->_values['_key'], $type, $direction);
    }

    /**
     * Validate document data before saving
     */
    public function validate(): bool
    {
        foreach ($this->_struct as $key => $default) {
            if (!isset($this->_values[$key]) && $default !== '') {
                $this->_values[$key] = $default;
            }
        }
        return true;
    }

    public function toArray(): array
    {
        return $this->_values;
    }

    // ArrayAccess implementation
    public function offsetSet($key, $value): void
    {
        if ($key === null)
            throw new \RuntimeException('Cannot set value without a key');


        // ArangoDB has reserved system fields that should not be modified directly
        if (in_array($key, ['_id', '_key', '_rev', '_from', '_to']) || !isset($this->_struct[$key]) )
            throw new \RuntimeException("Cannot set system field or undefined field: {$key} in ".(string) $this->_collection);
        
        $this->_values[$key] = $value;
        
        // Save changes if we're in connected mode - use AQL query
        if ($this->_connected && $this->_collection && isset($this->_values['_key']))
            $this->sync();
    }

    public function offsetGet($key): mixed
    {
        return $this->_values[$key] ?? $this->_struct[$key] ?? null;
    }

    public function offsetExists($key): bool
    {
        return isset($this->_values[$key]) || isset($this->_struct[$key]);
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

        $queryData = $this->_connection->buildAqlQuery('upsert', $this->_values, [],
                                                       (string) $this->_collection,
                                                       upsert_key: 'message-id');

        if( defined('_DEBUG') )
            error_log('SYNC: '.print_r($queryData,true));

        $result = $this->_connection->aql($queryData['query'],$queryData['bindVars']);

        // Update revision
        if (isset($result['_rev']))
            $this->_values['_rev'] = $result['_rev'];
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

    // /**
    //  * Set connection mode (connected or disconnected)
    //  */
    // public function setConnected(bool $connected): void
    // {
    //     $this->_connected = $connected;
    // }

    // /**
    //  * Get connection mode
    //  */
    // public function isConnected(): bool
    // {
    //     return $this->_connected;
    // }

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

