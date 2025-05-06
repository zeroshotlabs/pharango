<?php declare(strict_types=1);


class CollectionHandler extends Handler
{
    use db_data_common;

    protected string $documentClass = 'Collection';

    public function __construct($connection) {
        parent::__construct($connection);
    }

    public function getById($collectionName) {
        try {
            $result = $this->make_request('GET', "collection/{$collectionName}");
            return $this->createDocument($result);
        } catch (exception $e) {
            if ($e->getMessage() === 'collection not found') {
                return null;
            }
            throw $e;
        }
    }

    public function create($collectionName, array $options = []) {
        $data = array_merge(['name' => $collectionName], $options);
        $result = $this->make_request('POST', 'collection', $data);
        return $this->createDocument($result);
    }

    public function remove($collectionName) {
        return $this->make_request('DELETE', "collection/{$collectionName}");
    }

    public function getProperties($collectionName) {
        return $this->make_request('GET', "collection/{$collectionName}/properties");
    }

    public function setProperties($collectionName, array $properties) {
        return $this->make_request('PUT', "collection/{$collectionName}/properties", $properties);
    }

    public function getCount($collectionName) {
        $result = $this->make_request('GET', "collection/{$collectionName}/count");
        return $result['count'] ?? 0;
    }

    public function truncate($collectionName) {
        return $this->make_request('PUT', "collection/{$collectionName}/truncate");
    }
}


class Collection {
    private $name;
    
    public function __construct($name) {
        $this->name = $name;
    }
    
    public function getName() {
        return $this->name;
    }
}


