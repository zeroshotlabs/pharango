<?php declare(strict_types=1);

namespace pharango;


class Cursor implements \Iterator, \Countable
{
    private Connection $connection;
    private array $result;
    private ?array $documents = null;
    private int $position = 0;
    private ?Collection $collection = null;
    private ?string $id = null;
    private bool $hasMore = false;


    public function __construct(Connection $connection, array $result, ?Collection $collection = null)
    {
        $this->connection = $connection;
        $this->result = $result;
        $this->collection = $collection;
        
        // Store cursor ID if available for batch processing
        $this->id = $result['id'] ?? null;
        $this->hasMore = $result['hasMore'] ?? false;
    }
    
    public function count(): int
    {
        return $this->result['count'] ?? count($this->getAll());
    }
    
    public function getAll(): array
    {
        if ($this->documents === null) {
            $this->documents = [];
            if (isset($this->result['result'])) {
                foreach ($this->result['result'] as $doc) {
                    $this->documents[] = new Document($doc, $this->collection);
                }
            }
        }
        return $this->documents;
    }

    // Get raw documents without Document wrapper
    public function getRawDocuments(): array
    {
        return $this->result['result'] ?? [];
    }

    // Get document at specific position
    public function getDocument(int $position): ?Document
    {
        $documents = $this->getAll();
        return $documents[$position] ?? null;
    }

    // Check if cursor has more batches
    public function hasMore(): bool
    {
        return $this->hasMore;
    }

    // Fetch next batch of data if available
    public function fetchNextBatch(): bool
    {
        if (!$this->hasMore || $this->id === null) {
            return false;
        }

        $result = $this->connection->aql("FETCH CURSOR {$this->id}");
        $this->result = $result;
        $this->hasMore = $result['hasMore'] ?? false;
        $this->documents = null;
        return true;

        
        // try {
        //     $result = $this->connection->make_request('PUT', "cursor/{$this->id}");
            
        //     // Append new results to existing ones
        //     if (isset($result['result']) && is_array($result['result'])) {
        //         if (isset($this->result['result']) && is_array($this->result['result'])) {
        //             $this->result['result'] = array_merge($this->result['result'], $result['result']);
        //         } else {
        //             $this->result['result'] = $result['result'];
        //         }
        //     }
            
        //     // Update cursor state
        //     $this->hasMore = $result['hasMore'] ?? false;
            
        //     // Force reloading documents
        //     $this->documents = null;
            
        //     return true;
        // } catch (\Exception $e) {
        //     return false;
        // }
    }

    // Delete cursor on server
    public function dispose(): bool
    {
        if ($this->id === null) {
            return true;
        }

        $result = $this->connection->aql("DELETE CURSOR {$this->id}");
        $this->id = null;
        $this->hasMore = false;
        return true;
        
        // try {
        //     $this->connection->make_request('DELETE', "cursor/{$this->id}");
        //     $this->id = null;
        //     $this->hasMore = false;
        //     return true;
        // } catch (\Exception $e) {
        //     return false;
        // }
    }

    // Fetch all remaining batches
    public function fetchAll(): bool
    {
        $fetched = false;
        
        while ($this->hasMore()) {
            $result = $this->fetchNextBatch();
            if ($result) {
                $fetched = true;
            } else {
                break;
            }
        }
        
        return $fetched;
    }

    // Iterator implementation
    public function current(): mixed
    {
        return $this->getAll()[$this->position] ?? null;
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        $this->position++;
        
        // Automatically fetch next batch if needed
        if ($this->hasMore && $this->position >= count($this->getAll())) {
            $this->fetchNextBatch();
        }
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        return isset($this->getAll()[$this->position]);
    }

    // Get stats about the query if available
    public function getStats(): ?array
    {
        return $this->result['extra']['stats'] ?? null;
    }

    // Get warnings about the query if available
    public function getWarnings(): ?array
    {
        return $this->result['extra']['warnings'] ?? null;
    }

    // Auto dispose when object is destroyed
    public function __destruct()
    {
        $this->dispose();
    }
}

