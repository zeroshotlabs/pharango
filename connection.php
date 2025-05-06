<?php declare(strict_types=1);


class Connection
{
    use db_data_common;
    public string $host;
    public int $port;
    public string $username;
    public string $password;
    public string $database;

    public function __construct(array $options)
    {
        $this->host = $options['host'] ?? 'localhost';
        $this->port = $options['port'] ?? 8529;
        $this->username = $options['username'] ?? 'root';
        $this->password = $options['password'] ?? '';
        $this->database = $options['database'] ?? '_system';
    }

    public function use_db(string $database) {
        $this->database = $database;
    }

    public function get_databases() {
        return $this->init_request('GET', 'database', null, '_system')['result'] ?? [];
    }
}


