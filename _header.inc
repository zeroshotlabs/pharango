<?php declare(strict_types=1);
namespace pharango;

/* still with the age bug
Ensuring database 'test' exists...
Database 'test' is ready.
Ensuring collection 'users' exists...
Collection 'users' is ready.
Created user with key: 149557
Updated age to: 
Found John: john@example.com age: 
Deleted user
*/

// namespace arangodb;

// $connectionOptions = [
//     "host" => "localhost",
//     "port" => 8529,
//     "username" => "root",
//     "password" => "xxx",
//     "database" => "_system",
//     "timeout" => 30,
//     "connection" => "Keep-Alive",
//     "endpoint" => "tcp://localhost:8529",
//     "Authorization" => "Basic " . base64_encode("root:")
// ];
// ';
    


// // Example usage:
// /*
// // Initialize connection
// $connection = new Connection([
//     'host' => 'localhost',
//     'port' => 8529,
//     'username' => 'root',
//     'password' => 'xxx',
//     'database' => 'hoppr'
// ]);


// Exception class for server errors
class pharango_exception extends \Exception {
    protected int $httpCode;
    
    public function __construct(string $message, int $httpCode = 500) {
        parent::__construct($message);
        $this->httpCode = $httpCode;
    }
    
    public function getHttpCode(): int {
        return $this->httpCode;
    }
}

class ServerException extends pharango_exception {}

class ClientException extends pharango_exception {}

class ConnectException extends pharango_exception {}


// Include required files
require __DIR__ . '/src/traits.php';
require __DIR__ . '/src/connection.php';
require __DIR__ . '/src/document.php';
require __DIR__ . '/src/collection.php';
require __DIR__ . '/src/cursor.php';
require __DIR__ . '/src/graph.php';
require __DIR__ . '/src/graph_document.php';
require __DIR__ . '/src/graph_collection.php';


// // Test edge cases
// try {
//     $collection->findByMessageId('');  // Should throw InvalidArgumentException
// } catch (\InvalidArgumentException $e) {
//     // Expected
// }

// // Test invalid edge definitions
// try {
//     $collection->_edgeDefinitions = [];  // Should throw RuntimeException
//     $collection->getRelationships('key', 'type');
// } catch (\RuntimeException $e) {
//     // Expected
// }

// // Test AQL error handling
// try {
//     $collection->getRelationships('invalid_key', 'type');
// } catch (\RuntimeException $e) {
//     // Should contain AQL error message
// }

