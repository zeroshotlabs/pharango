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

trait base_trait
{
    // public function aql(string $query): array
    // {
    //     return $this->make_request('POST', 'cursor', [
    //         'query' => $query
    //     ]);
    // }
    public function aql(string $query): array
    {
        if( !empty($this->connection) )
            return $this->connection->aql($query);

        throw new \Exception('No connection found for aql query: '.$query);
    }
}

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
require __DIR__ . '/src/connection.php';
require __DIR__ . '/src/document.php';
require __DIR__ . '/src/collection.php';
require __DIR__ . '/src/cursor.php';


