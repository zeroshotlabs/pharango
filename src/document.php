<?php declare(strict_types=1);

class Document implements \ArrayAccess, \JsonSerializable
{
    protected array $_values = [];
    protected ?Collection $_collection = null;

    public function __construct(?array $data = null, ?Collection $collection = null)
    {
        $this->_collection = $collection;
        if (is_array($data)) {
            $this->_values = $data;
        }
    }

    // ArrayAccess implementation
    public function offsetSet($key, $value): void
    {
        if ($key === null) {
            throw new \RuntimeException('Cannot set value without a key');
        }
        if (in_array($key, ['_id', '_key', '_rev'])) {
            throw new \RuntimeException("Cannot set system field: {$key}");
        }
        $this->_values[$key] = $value;
        
        // Save changes if we have a collection
        if ($this->_collection) {
            if (isset($this->_values['_key'])) {
                // Update existing document
                $result = $this->_collection->getConnection()->make_request(
                    'PATCH',
                    "document/{$this->_collection->getCollectionName()}/{$this->_values['_key']}",
                    ['document' => $this->_values]
                );
                $this->_values = $result;
            }
            else
            {
                throw new Exception('DOCUMENT KEY NOT SET for "$key" => "$value"');
                // Create new document
                $result = $this->_collection->getConnection()->make_request(
                    'POST',
                    "document/{$this->_collection->getCollectionName()}",
                    $this->_values
                );
                $this->_values = $result;
            }
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
        if (in_array($key, ['_id', '_key', '_rev'])) {
            throw new \RuntimeException("Cannot remove system field: {$key}");
        }
        unset($this->_values[$key]);
        
        // Save changes if we have a collection and document exists
        if ($this->_collection && isset($this->_values['_key'])) {
            $result = $this->_collection->getConnection()->make_request(
                'PATCH',
                "document/{$this->_collection->getCollectionName()}/{$this->_values['_key']}",
                ['document' => $this->_values]
            );
            $this->_values = $result;
        }
    }

    // JsonSerializable implementation
    public function jsonSerialize(): array
    {
        return $this->_values;
    }

    public function getAll(): array
    {
        return $this->_values;
    }

    public function save(): void
    {
        if (!$this->_collection) {
            throw new \RuntimeException('Cannot save document without a collection');
        }
        
        if (isset($this->_values['_key'])) {
            $this->_values = $this->_collection->getConnection()->make_request(
                'PATCH',
                "document/{$this->_collection->getCollectionName()}/{$this->_values['_key']}",
                ['document' => $this->_values]
            );
        } else {
            $this->_values = $this->_collection->getConnection()->make_request(
                'POST',
                "document/{$this->_collection->getCollectionName()}",
                $this->_values
            );
        }
    }

    public function setCollection(?Collection $collection): void
    {
        $this->_collection = $collection;
    }

    public function getCollection(): ?Collection
    {
        return $this->_collection;
    }
}

