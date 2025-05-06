<?php declare(strict_types=1);



trait ArangoRealtimePersistenceTrait
{
    public bool $realtimePersistence = true;
    public ?\Closure $persistCallback = null; // function($field, $value, $allFields)


    // Called when a field is changed
    protected function onFieldChange($field, $value)
    {
        if ($this->realtimePersistence && $this->persistCallback) {
            // Persist only the changed field (or all fields, as needed)
            call_user_func($this->persistCallback, $field, $value, $this->_values);
        }
    }
}

trait db_data_common
{
    protected function make_request(string $method, string $path, ?array $data = null, ?string $db = null): array
    {
        $db = $db ?? $this->connection->database or throw new exception('Database not set');
        $url = "http://{$this->connection->host}:{$this->connection->port}/_db/{$db}/_api/{$path}";

        $headers = [
            "Authorization: Basic " . base64_encode("{$this->connection->username}:{$this->connection->password}")
        ];
        
        if ($data !== null)
            $headers[] = "Content-Type: application/json";
        
        $options = [
            "http" => [
                "method" => $method,
                "header" => implode("\r\n", $headers),
                "ignore_errors" => true
            ]
        ];
        
        if ($data !== null) {
            $options["http"]["content"] = json_encode($data);
        }
        
        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        $result = json_decode($response, true);
        
        if (!empty($result['error']))
            throw new exception($result['errorMessage'], $result['code']);
        
        return $result;
    }
}

abstract class Handler
{
    use db_data_common;

    protected $connection;
    protected string $documentClass;

    public function __construct($connection)
    {
        if( empty($this->documentClass))
            throw new exception('Document class not set');

        $this->connection = $connection;
    }

    // public function setDocumentClass($documentClass)
    // {
    //     $this->documentClass = $documentClass;
    // }

    protected function init_document($data) {
        $class = $this->documentClass;
        $document = new $class($data);
        if (isset($data['_id']))
            $document->setId($data['_id']);

        if (isset($data['_rev']))
            $document->setRev($data['_rev']);

        $document->setIsNew(false);
        return $document;
    }

    protected function getCollectionName($document) {
        return explode('/', $document->getId())[0];
    }

    protected function getDocumentKey($document) {
        return $document->getInternalKey();
    }
}




class ServerException extends \Exception {
    private $serverMessage;
    
    public function __construct($message, $code = 0, ?\Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->serverMessage = $message;
    }
    
    public function getServerMessage() {
        return $this->serverMessage;
    }
}

class ClientException extends exception {}

class ConnectException extends exception {}


require('libarangodb.php');



// $connectionOptions = [
//     "host" => "localhost",
//     "port" => 8529,
//     "username" => "root",
//     "password" => "zaun3r3",
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
//     'password' => '',
//     'database' => 'hoppr'
// ]);
