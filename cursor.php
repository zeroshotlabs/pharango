<?php declare(strict_types=1);


class Cursor {
    private $connection;
    private $result;
    private $documentClass;
    private $documents = null;
    
    public function __construct($connection, $result, $documentClass) {
        $this->connection = $connection;
        $this->result = $result;
        $this->documentClass = $documentClass;
    }
    
    public function getCount() {
        return $this->result['count'] ?? 0;
    }
    
    public function getAll() {
        if ($this->documents === null)
        {
            $this->documents = [];
            if (isset($this->result['result']))
            {
                foreach ($this->result['result'] as $doc)
                {
                    $document = new $this->documentClass($doc);

                    if (isset($doc['_id']))
                        $document->setId($doc['_id']);

                    if (isset($doc['_rev']))
                        $document->setRev($doc['_rev']);

                    $document->setIsNew(false);
                    $this->documents[] = $document;
                }
            }
        }
        return $this->documents;
    }
}

