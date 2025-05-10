<?php declare(strict_types=1);

namespace pharango;

use pharango\ClientException as client_exception;

/**
 * Graph class for managing ArangoDB graph operations
 */
class Graph
{
    use _pharango;
    
    protected Connection $_connection;
    protected string $_name;
    protected array $_edge_definitions;
    protected array $_orphan_collections;
    
    /**
     * Create a new Graph instance
     * 
     * @param Connection $connection The database connection
     * @param string $name The graph name
     * @param array $edge_definitions Edge collection definitions
     * @param array $orphan_collections Orphan collections
     */
    public function __construct(Connection $connection, string $name = '', array $edge_definitions = [], array $orphan_collections = [])
    {
        $this->_connection = $connection;
        $this->_name = empty($name) ? get_class($this) : $name;
        $this->_edge_definitions = $edge_definitions;
        $this->_orphan_collections = $orphan_collections;
    }
    
    /**
     * Ensure the graph exists, create if it doesn't
     * 
     * @return bool True if graph exists or was created successfully
     * @throws client_exception If graph creation fails
     */
    public function ensure_exists(): bool
    {
        // Check if graph exists
        $result = $this->_connection->aql("FOR g IN GRAPHS() RETURN g.name");
        
        if (in_array($this->_name, $result)) {
            return true;
        }
        
        // Create graph if it doesn't exist
        $query = "LET graph = {
            name: @graphName,
            edgeDefinitions: @edgeDefinitions,
            orphanCollections: @orphanCollections
        }
        RETURN GRAPH_CREATE(graph.name, graph.edgeDefinitions, graph.orphanCollections)";
        
        $bindVars = [
            'graphName' => $this->_name,
            'edgeDefinitions' => $this->_edge_definitions,
            'orphanCollections' => $this->_orphan_collections
        ];
        
        $result = $this->_connection->aql($query, $bindVars);
        
        if (isset($result['error'])) {
            throw new client_exception("Failed to create graph: " . $result['error']);
        }
        
        return true;
    }
    
    /**
     * Create a relationship between two documents
     * 
     * @param string $from_id Source document ID
     * @param string $to_id Target document ID
     * @param string $type Relationship type
     * @param array $metadata Additional relationship metadata
     * @return array The created relationship
     * @throws client_exception If relationship creation fails
     */
    public function create_relationship(string $from_id, string $to_id, string $type, array $metadata = []): array
    {
        if (empty($from_id) || empty($to_id) || empty($type)) {
            throw new client_exception("from_id, to_id, and type are required");
        }
        
        $query = "INSERT {
            _from: @fromId,
            _to: @toId,
            type: 'relationship',
            created: DATE_NOW(),
            relationship_type: @type,
            metadata: @metadata,
            uid: @uid
        } INTO @@edgeCollection
        RETURN NEW";
        
        $bindVars = [
            'fromId' => $from_id,
            'toId' => $to_id,
            'type' => $type,
            'metadata' => json_encode($metadata),
            'uid' => uniqid(),
            '@edgeCollection' => $this->_name . '_edges'
        ];
        
        $result = $this->_connection->aql($query, $bindVars);
        
        if (isset($result['error'])) {
            throw new client_exception("Failed to create relationship: " . $result['error']);
        }
        
        return $result;
    }
    
    /**
     * Get relationships for a document
     * 
     * @param string $document_id Document ID to get relationships for
     * @param string|null $type Optional relationship type filter
     * @param string $direction Traversal direction (OUTBOUND/INBOUND/ANY)
     * @return array The relationships
     * @throws client_exception If query fails
     */
    public function get_relationships(string $document_id, ?string $type = null, string $direction = 'OUTBOUND'): array
    {
        if (empty($document_id)) {
            throw new client_exception("document_id is required");
        }
        
        if (!in_array($direction, ['OUTBOUND', 'INBOUND', 'ANY'])) {
            throw new client_exception("Invalid direction: {$direction}");
        }
        
        $query = "FOR v, e, p IN 1..1 {$direction} @documentId
                 GRAPH @graphName";
                 
        if ($type !== null) {
            $query .= " FILTER e.relationship_type == @type";
        }
        
        $query .= " RETURN { vertex: v, edge: e, path: p }";
        
        $bindVars = [
            'documentId' => $document_id,
            'graphName' => $this->_name,
            'type' => $type
        ];
        
        $result = $this->_connection->aql($query, $bindVars);
        
        if (isset($result['error'])) {
            throw new client_exception("Failed to get relationships: " . $result['error']);
        }
        
        return $result;
    }
    
    /**
     * Find path between two documents
     * 
     * @param string $from_id Source document ID
     * @param string $to_id Target document ID
     * @param string|null $type Optional relationship type filter
     * @param int $min_depth Minimum path depth
     * @param int $max_depth Maximum path depth
     * @return array The path information
     * @throws client_exception If query fails
     */
    public function find_path(string $from_id, string $to_id, ?string $type = null, int $min_depth = 1, int $max_depth = 3): array
    {
        if (empty($from_id) || empty($to_id)) {
            throw new client_exception("from_id and to_id are required");
        }
        
        if ($min_depth < 1 || $max_depth < $min_depth) {
            throw new client_exception("Invalid depth parameters");
        }
        
        $query = "FOR v, e, p IN @minDepth..@maxDepth OUTBOUND @fromId
                 GRAPH @graphName
                 FILTER v._id == @toId";
                 
        if ($type !== null) {
            $query .= " FILTER e.relationship_type == @type";
        }
        
        $query .= " RETURN { path: p, distance: LENGTH(p.edges) }
                  SORT LENGTH(p.edges) ASC
                  LIMIT 1";
        
        $bindVars = [
            'fromId' => $from_id,
            'toId' => $to_id,
            'graphName' => $this->_name,
            'type' => $type,
            'minDepth' => $min_depth,
            'maxDepth' => $max_depth
        ];
        
        $result = $this->_connection->aql($query, $bindVars);
        
        if (isset($result['error'])) {
            throw new client_exception("Failed to find path: " . $result['error']);
        }
        
        return $result;
    }
    
    /**
     * Get message thread
     * 
     * @param string $message_id Message ID to get thread for
     * @param int $max_depth Maximum thread depth
     * @return array The thread information
     * @throws client_exception If query fails
     */
    public function get_thread(string $message_id, int $max_depth = 5): array
    {
        if (empty($message_id)) {
            throw new client_exception("message_id is required");
        }
        
        if ($max_depth < 1) {
            throw new client_exception("max_depth must be greater than 0");
        }
        
        $query = "FOR v, e, p IN 1..@maxDepth ANY @messageId
                 GRAPH @graphName
                 FILTER e.message_id != null
                 COLLECT threadId = e.message_id
                 INTO thread
                 RETURN {
                     message_id: threadId,
                     documents: thread[*].v,
                     relationships: thread[*].e,
                     depth: LENGTH(thread[0].p.edges)
                 }
                 SORT thread[0].e.created ASC";
        
        $bindVars = [
            'messageId' => $message_id,
            'graphName' => $this->_name,
            'maxDepth' => $max_depth
        ];
        
        $result = $this->_connection->aql($query, $bindVars);
        
        if (isset($result['error'])) {
            throw new client_exception("Failed to get thread: " . $result['error']);
        }
        
        return $result;
    }
    
    /**
     * Delete a relationship
     * 
     * @param string $edge_key The relationship key to delete
     * @return bool True if deletion was successful
     * @throws client_exception If deletion fails
     */
    public function delete_relationship(string $edge_key): bool
    {
        if (empty($edge_key)) {
            throw new client_exception("edge_key is required");
        }
        
        $query = "REMOVE @edgeKey IN @@edgeCollection RETURN OLD";
        $bindVars = [
            'edgeKey' => $edge_key,
            '@edgeCollection' => $this->_name . '_edges'
        ];
        
        $result = $this->_connection->aql($query, $bindVars);
        
        if (isset($result['error'])) {
            throw new client_exception("Failed to delete relationship: " . $result['error']);
        }
        
        return true;
    }
}


