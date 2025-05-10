<?php declare(strict_types=1);

namespace pharango;



class Connection
{
    use _pharango;
    
    protected string $host;
    protected int $port;
    protected string $username;
    protected string $password;
    protected string $database;
    private bool $connected = false;
    private bool $has_options = false;

    public function __construct(array $options)
    {
        $this->use_options($options);
    }

    public function use_options(array $options): void
    {
        $this->host = $options['host'] ?? 'localhost';
        $this->port = (int) $options['port'] ?? 8529;

        if( empty($options['username']) || empty($options['password']) )
            throw new ServerException('Username and password are required');

        $this->username = $options['username'];
        $this->password = $options['password'];
        $this->database = $options['database'] ?? '_system';

        $this->has_options = true;
    }

    public function connect( ?array $options = null): bool
    {
        if( $options !== null )
            $this->use_options($options);

        try {
            $url = $this->build_url('version');
            $this->aql($url);
            $this->connected = true;
            return true;
        } catch (\Exception $e) {
            $this->connected = false;
            throw new ServerException('Connection failed: ' . $e->getMessage());
        }
    }

    // public function has_db(string $database): bool
    // {
    //     $databases = $this->aql('GET', 'database')['result'] ?? [];
    //     return in_array($database, $databases);
    // }

    public function build_url(string $url = ''): string
    {
        return "http://{$this->host}:{$this->port}/_db/{$this->database}/_api/{$url}";
    }


    //     There are two types of bind parameters:
    //     - bind parameter names starting with a single @ character, e.g. @count, @value, @test:
    //       These can be used to ship any JSON-encodable data to the server.
    //     - bind parameter names starting with two @ characters always refer to collections, e.g. @@collections, @@c:
    //       These can be used as placeholders for collection names.
    //   */
    //  FOR doc IN @@collection
    //    FILTER doc.value == @value
    //    RETURN doc

    /**
     * Build an AQL query with proper parameter binding
     */
    public function buildAqlQuery(string $operation, array $data = [], array $options = [], string $collection_name = ''): array
    {
        $query = '';
        $bindVars = [];

        switch ($operation) {
            case 'find':
                $query = "FOR doc IN @@collection";
                
                if (!empty($data)) {
                    $query .= " FILTER ";
                    $conditions = [];
                    
                    foreach ($data as $key => $value) {
                        $conditions[] = "doc.{$key} == @{$key}";
                        $bindVars[$key] = $value;
                    }
                    
                    $query .= implode(" AND ", $conditions);
                }

                if (!empty($options['limit'])) {
                    $query .= " LIMIT @limit";
                    $bindVars['limit'] = $options['limit'];
                }
                
                $query .= " RETURN doc";
                break;

            case 'insert':
                $fields = [];
                foreach ($data as $key => $value) {
                    $fields[] = "{$key}: @{$key}";
                    $bindVars[$key] = $value;
                }
                
                $query = "INSERT { " . implode(", ", $fields) . " } INTO @@collection RETURN NEW";
                break;
            
            case 'update':
                $fields = [];
                foreach ($data as $key => $value) {
                    $fields[] = "{$key}: @{$key}";
                    $bindVars[$key] = $value;
                }

                $query = "UPDATE {_key: '{$data['_key']}'} WITH { " . implode(", ", $fields) . " } INTO @@collection RETURN NEW";
                break;
        }

        $bindVars['@collection'] = $data['_collection']??$collection_name;
        
        return [
            'query' => $query,
            'bindVars' => $bindVars
        ];
    }

    /**
     * Execute an AQL query and return the results
     * 
     * @param string $aql The AQL query string
     * @param array $bindVars Bind variables for the query
     * @return array|null The query results or null if error
     */
    function aql(string $aql, array $bindVars = []): ?array 
    {        
        // Build the API endpoint URL
        $url = $this->build_url('cursor');
        
        // Prepare the request data
        $data = json_encode([
            'query' => $aql,
            'bindVars' => (object) $bindVars,
            'batchSize' => 1000
        ]);
//        var_dump($data);
        // Initialize cURL
        $ch = curl_init();
        
        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($this->username . ':' . $this->password)
        ]);

        // Execute the request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Handle errors
        if (curl_errno($ch)) {
            $error = 'ArangoDB query error: ' . curl_error($ch);
            curl_close($ch);
            error_log($error);
            throw new ServerException($error, 500);
        }
        
        // Parse response
        $result = json_decode($response, true);
        // Check for errors in the response
        if ( $result['error'] || !isset($result['result']))
        {
            $errorMessage = $result['errorMessage'] ?? 'Unknown error';
            $errorCode = $result['errorNum'] ?? $httpCode;
            error_log("ArangoDB error {$errorCode}: {$aql} | {$errorMessage}");
            throw new ServerException($aql.' | '.$errorMessage, $errorCode);
        }
        
        return $result['result'];
    }

    public function make_request(string $method, string $url, array $data = []): array
    {
        $ch = curl_init();
        $fullUrl = $this->build_url($url);
        curl_setopt($ch, CURLOPT_URL, $fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        // Always include Authorization header
        $auth = base64_encode($this->username . ':' . $this->password);
        $headers[] = "Authorization: Basic {$auth}";
        
        if (!empty($data))
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch))
            throw new ServerException('Connection error: ' . curl_error($ch), 0);
        
        curl_close($ch);
        
        if ($httpCode === 401)
            throw new ServerException('Authentication failed. Please check your username and password.', 401);
        
        if ($httpCode === 404)
            throw new ServerException('Resource not found: ' . $url, 404);
        
        if ($httpCode === 409)
            throw new ServerException('Conflict: The operation conflicts with the current state of the resource.', 409);
        
        if ($httpCode >= 400)
        {
            $error = json_decode($response, true);
            $errorMessage = $error['errorMessage'] ?? "HTTP Error: $httpCode";
            $errorNum = $error['errorNum'] ?? 0;
            throw new ServerException("$errorMessage (Error code: $errorNum)", $httpCode);
        }
        
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE)
            throw new ServerException('Invalid JSON response: ' . json_last_error_msg(), 500);
        
        return $result ?? [];
    }
}


