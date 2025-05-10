<?php declare(strict_types=1);

namespace pharango;


trait _pharango
{
    // public function aql(string $query): array
    // {
    //     return $this->make_request('POST', 'cursor', [
    //         'query' => $query
    //     ]);
    // }
    public function aql(string $query): array
    {
        if( !empty($this->_connection) )
            return $this->_connection->aql($query);

        throw new \Exception('No connection found for aql query: '.$query);
    }
}

/**
 * Trait for common collection functionality
 */
trait GraphCollectionTrait
{
    /**
     * Validate edge definitions
     */
    protected function validateEdgeDefinitions(): void
    {
        if (empty($this->_edgeDefinitions)) {
            throw new \RuntimeException('Edge definitions are required for graph collections');
        }
        
        foreach ($this->_edgeDefinitions as $def) {
            if (!isset($def['collection']) || !isset($def['from']) || !isset($def['to'])) {
                throw new \RuntimeException('Invalid edge definition: missing required fields');
            }
            if (!is_array($def['from']) || !is_array($def['to'])) {
                throw new \RuntimeException('Invalid edge definition: from/to must be arrays');
            }
        }
    }

    /**
     * Execute AQL query with error handling
     */
    protected function executeAql(string $query, array $bindVars): array
    {
        try {
            $result = $this->_connection->aql($query, $bindVars);
            if (isset($result['error']) && $result['error']) {
                throw new \RuntimeException("AQL query failed: " . ($result['errorMessage'] ?? 'Unknown error'));
            }
            return $result;
        } catch (\Exception $e) {
            throw new \RuntimeException("AQL query failed: " . $e->getMessage());
        }
    }

    /**
     * Find document by message ID
     */
    public function findByMessageId(string $messageId): ?document
    {
        if (empty($messageId)) {
            throw new \InvalidArgumentException('Message ID cannot be empty');
        }
        $result = $this->find(['message-id' => $messageId], 1);
        return $result[0] ?? null;
    }
    
    /**
     * Get relationships of a specific type
     */
    protected function getRelationships(string $key, string $type, string $direction = 'INBOUND'): array
    {
        if (empty($key)) {
            throw new \InvalidArgumentException('Document key cannot be empty');
        }
        if (empty($type)) {
            throw new \InvalidArgumentException('Relationship type cannot be empty');
        }
        if (!in_array($direction, ['INBOUND', 'OUTBOUND', 'ANY'])) {
            throw new \InvalidArgumentException('Invalid direction: must be INBOUND, OUTBOUND, or ANY');
        }

        $this->validateEdgeDefinitions();
        
        $query = "FOR v, e, p IN 1..1 {$direction} @key {$this->_edgeDefinitions[0]['collection']} 
                 FILTER e.type == @type 
                 RETURN v";
        return $this->executeAql($query, [
            'key' => $key,
            'type' => $type
        ]);
    }
    
    /**
     * Get all related documents
     */
    protected function getRelatedDocumentsInternal(string $key, string $direction = 'ANY'): array
    {
        if (empty($key)) {
            throw new \InvalidArgumentException('Document key cannot be empty');
        }
        if (!in_array($direction, ['INBOUND', 'OUTBOUND', 'ANY'])) {
            throw new \InvalidArgumentException('Invalid direction: must be INBOUND, OUTBOUND, or ANY');
        }

        $this->validateEdgeDefinitions();
        
        $query = "FOR v, e, p IN 1..1 {$direction} @key {$this->_edgeDefinitions[0]['collection']} 
                 RETURN {document: v, relationship: e}";
        return $this->executeAql($query, ['key' => $key]);
    }

    /**
     * Ensure edge collections exist
     */
    protected function ensureEdgeCollections(): void
    {
        $this->validateEdgeDefinitions();
        
        foreach ($this->_edgeDefinitions as $definition) {
            $edgeCollection = new collection(
                $this->_connection,
                $definition['collection'],
                '',
                $this->_auto_ddl
            );
            $edgeCollection->ensure_exists();
        }
    }
}
