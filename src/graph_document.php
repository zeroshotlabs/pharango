<?php declare(strict_types=1);

namespace pharango;


/**
 * Base document class with graph support
 */
abstract class GraphDocument extends Document
{
    protected string $_graphName = 'hop_graph';
    protected array $_edgeDefinitions = [];
    
    public function __construct(?Collection $collection = null, array|\Traversable|null $data = null)
    {
        parent::__construct($collection, $data);
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
            throw new \RuntimeException('Cannot create relationship without document key');
        }
        return $this->getGraph()->create_relationship($this->_values['_key'], $toKey, $type, $metadata);
    }
    
    /**
     * Get relationships from this document
     */
    public function getRelationships(?string $type = null, string $direction = 'OUTBOUND'): array
    {
        if (!isset($this->_values['_key'])) {
            throw new \RuntimeException('Cannot get relationships without document key');
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
}
