<?php declare(strict_types=1);

namespace pharango;


/**
 * Base collection class with graph support
 */
abstract class GraphCollection extends Collection
{
    protected array $_edgeDefinitions = [];
    protected string $_graphName = 'hop_graph';
    
    public function __construct(connection $connection, string $name = '', string $doc_name = '', bool $auto_ddl = true)
    {
        parent::__construct($connection, $name, $doc_name, $auto_ddl);
        
        // Ensure edge collections exist
        if ($this->_auto_ddl) {
            $this->ensure_edge_collections();
        }
    }
    
    /**
     * Ensure edge collections exist
     */
    protected function ensure_edge_collections(): void
    {
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
    
    /**
     * Create a new document with validation
     */
    public function createDocument(array $data): Document
    {
        $doc = new ($this->_doc_name)($this, $data);
        $doc->validate();
        $doc['_key'] = $doc['_key']??$doc['message-id'];
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

    /**
     * Get relationships of a specific type
     */
    public function getRelationships(string $key, string $type): array
    {
        $query = "FOR v, e, p IN 1..1 OUTBOUND @start GRAPH @graphName 
                 FILTER e.type == @type 
                 RETURN v";
        
        $result = $this->_connection->aql($query, [
            'start' => $this->_name . '/' . $key,
            'graphName' => $this->_graphName,
            'type' => $type
        ]);

        return $result['result'] ?? [];
    }

    /**
     * Get all related documents
     */
    protected function getRelatedDocumentsInternal(string $key): array
    {
        $query = "FOR v, e, p IN 1..1 OUTBOUND @start GRAPH @graphName 
                 RETURN v";
        
        $result = $this->_connection->aql($query, [
            'start' => $this->_name . '/' . $key,
            'graphName' => $this->_graphName
        ]);

        return $result['result'] ?? [];
    }

    /**
     * Find document by message ID
     */
    public function findByMessageId(string $messageId): array
    {
        $result = $this->find(['message-id' => $messageId], 1);
        return $result[0] ?? [];
    }

    /**
     * Create a relationship between documents
     */
    public function createRelationship(string $fromKey, string $toCollection, string $toKey, string $type, array $attributes = []): array|Document
    {
        $edgeCollection = $this->getEdgeCollection($toCollection);
        if (!$edgeCollection) {
            throw new \RuntimeException("No edge collection defined for relationship to {$toCollection}");
        }

        $edge = array_merge([
            '_from' => "{$this->_name}/{$fromKey}",
            '_to' => "{$toCollection}/{$toKey}",
            'type' => $type
        ], $attributes);

        return $edgeCollection->insert($edge);
    }

    /**
     * Get the edge collection for a relationship
     */
    protected function getEdgeCollection(string $toCollection): ?Collection
    {
        foreach ($this->_edgeDefinitions as $definition) {
            if (in_array($toCollection, $definition['to'])) {
                return new Collection(
                    $this->_connection,
                    $definition['collection'],
                    '',
                    $this->_auto_ddl
                );
            }
        }
        return null;
    }

    /**
     * Check if a relationship exists
     */
    public function hasRelationship(string $fromKey, string $toCollection, string $toKey, string $type): bool
    {
        $query = "FOR e IN @edgeCollection
                 FILTER e._from == @from AND e._to == @to AND e.type == @type
                 LIMIT 1
                 RETURN e";
        
        $result = $this->_connection->aql($query, [
            'edgeCollection' => $this->getEdgeCollection($toCollection)->_name,
            'from' => "{$this->_name}/{$fromKey}",
            'to' => "{$toCollection}/{$toKey}",
            'type' => $type
        ]);

        return !empty($result['result']);
    }
}

