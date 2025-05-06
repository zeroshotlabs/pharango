<?php declare(strict_types=1);

class Connection
{
    protected string $host;
    protected int $port;
    protected string $username;
    protected string $password;
    protected string $database;
    private bool $connected = false;

    public function __construct(array $options)
    {
        $this->host = $options['host'] ?? 'localhost';
        $this->port = $options['port'] ?? 8529;
        $this->username = $options['username'] ?? 'root';
        $this->password = $options['password'] ?? '';
        $this->database = $options['database'] ?? '_system';
        
        $this->validate();
    }

    private function validate(): void
    {
        if (empty($this->host)) {
            throw new ServerException('Host cannot be empty');
        }
        if ($this->port <= 0 || $this->port > 65535) {
            throw new ServerException('Invalid port number');
        }
        if (empty($this->username)) {
            throw new ServerException('Username cannot be empty');
        }
    }

    public function connect(): bool
    {
        try {
            $url = "http://{$this->host}:{$this->port}/_db/{$this->database}/_api/version";
            $this->make_request('GET', $url);
            $this->connected = true;
            return true;
        } catch (\Exception $e) {
            $this->connected = false;
            throw new ServerException('Connection failed: ' . $e->getMessage());
        }
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function useDatabase(string $database): void
    {
        $this->database = $database;
        $this->connected = false; // Force reconnection with new database
    }

    public function getDatabases(): array
    {
        return $this->make_request('GET', 'database')['result'] ?? [];
    }

    public function databaseExists(string $database): bool
    {
        $databases = $this->getDatabases();
        return in_array($database, $databases);
    }

    public function createDatabase(string $database): bool
    {
        if ($this->databaseExists($database)) {
            return true;
        }

        $data = ['name' => $database];
        
        try {
            $this->make_request('POST', 'database', $data);
            return true;
        } catch (\Exception $e) {
            throw new ServerException("Failed to create database: " . $e->getMessage());
        }
    }

    public function ensureDatabase(string $database): bool
    {
        if (!$this->databaseExists($database)) {
            return $this->createDatabase($database);
        }
        return true;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function make_request(string $method, string $url, array $data = []): array
    {
        $ch = curl_init();
        $fullUrl = "http://{$this->host}:{$this->port}/_db/{$this->database}/_api/{$url}";
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
        
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            throw new ServerException('Connection error: ' . curl_error($ch), 0);
        }
        
        curl_close($ch);
        
        if ($httpCode === 401) {
            throw new ServerException('Authentication failed. Please check your username and password.', 401);
        }
        
        if ($httpCode === 404) {
            throw new ServerException('Resource not found: ' . $url, 404);
        }
        
        if ($httpCode === 409) {
            throw new ServerException('Conflict: The operation conflicts with the current state of the resource.', 409);
        }
        
        if ($httpCode >= 400) {
            $error = json_decode($response, true);
            $errorMessage = $error['errorMessage'] ?? "HTTP Error: $httpCode";
            $errorNum = $error['errorNum'] ?? 0;
            throw new ServerException("$errorMessage (Error code: $errorNum)", $httpCode);
        }
        
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ServerException('Invalid JSON response: ' . json_last_error_msg(), 500);
        }
        
        return $result ?? [];
    }
}
