<?php declare(strict_types=1);

class Document implements \ArrayAccess, \JsonSerializable
{
    protected ?string $_id = null;
    protected ?string $_key = null;
    protected ?string $_rev = null;
    protected bool $_isNew = true;
    protected array $_values = [];
    protected ?Collection $_collection = null;
    protected bool $_isUpdating = false;

    public function __construct(?array $data = null, ?Collection $collection = null)
    {
        $this->_collection = $collection;
        
        if (is_array($data)) {
            // Handle system fields first
            if (isset($data['_id'])) {
                $this->setId($data['_id']);
                unset($data['_id']);
            }
            if (isset($data['_key'])) {
                $this->setKey($data['_key']);
                unset($data['_key']);
            }
            if (isset($data['_rev'])) {
                $this->setRev($data['_rev']);
                unset($data['_rev']);
            }
            
            // Set user data
            foreach ($data as $key => $value) {
                $this->_values[$key] = $value;
            }
        }
    }

    public function getId(): ?string
    {
        return $this->_id;
    }

    public function setId(?string $id): void
    {
        $this->_id = $id;
        if ($id) {
            $parts = explode('/', $id);
            if (count($parts) > 1) {
                $this->_key = $parts[1];
            }
        }
    }

    public function getKey(): ?string
    {
        return $this->_key;
    }

    public function setKey(?string $key): void
    {
        $this->_key = $key;
    }

    public function getRev(): ?string
    {
        return $this->_rev;
    }

    public function setRev(?string $rev): void
    {
        $this->_rev = $rev;
    }

    public function isNew(): bool
    {
        return $this->_isNew;
    }

    public function setIsNew(bool $isNew): void
    {
        $this->_isNew = $isNew;
    }

    public function set(string $key, mixed $value): void
    {
        // Don't allow setting system fields directly
        if (in_array($key, ['_id', '_key', '_rev'])) {
            throw new \RuntimeException("Cannot set system field: {$key}");
        }
        $this->_values[$key] = $value;
    }

    public function get(string $key): mixed
    {
        // Handle system fields
        if ($key === '_id') return $this->_id;
        if ($key === '_key') return $this->_key;
        if ($key === '_rev') return $this->_rev;
        
        return $this->_values[$key] ?? null;
    }

    public function getAll(): array
    {
        $data = $this->_values;
        // Only include system fields if they exist
        if ($this->_id) $data['_id'] = $this->_id;
        if ($this->_key) $data['_key'] = $this->_key;
        if ($this->_rev) $data['_rev'] = $this->_rev;
        return $data;
    }

    public function getData(): array
    {
        return $this->_values;
    }

    public function remove(string $key): void
    {
        if (in_array($key, ['_id', '_key', '_rev'])) {
            throw new \RuntimeException("Cannot remove system field: {$key}");
        }
        unset($this->_values[$key]);
        if (!$this->_isNew) {
            $this->save();
        }
    }

    public function has(string $key): bool
    {
        if ($key === '_id') return $this->_id !== null;
        if ($key === '_key') return $this->_key !== null;
        if ($key === '_rev') return $this->_rev !== null;
        
        return isset($this->_values[$key]);
    }

    // ArrayAccess implementation
    public function offsetSet($key, $value): void
    {
        if ($key === null) {
            throw new \RuntimeException('Cannot set value without a key');
        }
        $this->set($key, $value);
        if (!$this->_isNew) {
            $this->save();
        }
    }

    public function offsetGet($key): mixed
    {
        return $this->get($key);
    }

    public function offsetExists($key): bool
    {
        return $this->has($key);
    }

    public function offsetUnset($key): void
    {
        $this->remove($key);
    }

    // JsonSerializable implementation
    public function jsonSerialize(): array
    {
        return $this->getAll();
    }

    public function setCollection(?Collection $collection): void
    {
        $this->_collection = $collection;
    }

    public function getCollection(): ?Collection
    {
        return $this->_collection;
    }

    // Manual save functionality (for batch operations)
    public function save(): void
    {
        if (!$this->_collection) {
            throw new \RuntimeException('No collection set for document');
        }

        if ($this->_isNew) {
            // Create new document
            $result = $this->_collection->getConnection()->make_request(
                'POST',
                "document/{$this->_collection->getCollectionName()}",
                $this->getData()
            );
            $this->setId($result['_id']);
            $this->setKey($result['_key']);
            $this->setRev($result['_rev']);
            $this->setIsNew(false);
        } else {
            // Update existing document with PATCH
            $result = $this->_collection->getConnection()->make_request(
                'PATCH',
                "document/{$this->_collection->getCollectionName()}/{$this->_key}",
                ['document' => $this->getData()]
            );
            $this->setRev($result['_rev']);
        }
    }

    // Batch update multiple fields at once
    public function update(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }
        if (!$this->_isNew) {
            $this->save();
        }
    }

    // Get document as array (alias for getAll)
    public function toArray(): array
    {
        return $this->getAll();
    }

    protected function persistField(string $key, mixed $value): void
    {
        if ($this->_collection && $this->_key && !$this->_isNew) {
            $this->_isUpdating = true;
            try {
                $this->_collection->update($this, [$key => $value]);
            } finally {
                $this->_isUpdating = false;
            }
        }
    }
}

