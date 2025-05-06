<?php declare(strict_types=1);

class Cursor implements \Iterator, \Countable
{
    private Connection $connection;
    private array $result;
    private ?array $documents = null;
    private int $position = 0;
    private ?Collection $collection = null;
    
    public function __construct(Connection $connection, array $result, ?Collection $collection = null)
    {
        $this->connection = $connection;
        $this->result = $result;
        $this->collection = $collection;
    }
    
    public function count(): int
    {
        return $this->result['count'] ?? 0;
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
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        return isset($this->getAll()[$this->position]);
    }
}

