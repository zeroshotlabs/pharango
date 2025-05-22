<?php

require_once "ArangoDBClient.php";

$client = new ArangoDBClient("localhost", 8529, "root", "zaun3r3", "_system");
$dbName = "test_database";
$collectionName = "test_collection";

// Create database if it doesn't exist
$result = $client->createDatabase($dbName);
if (isset($result['error']) && $result['error']) {
    echo "Database creation response: " . PHP_EOL;
    print_r($result);
    if ($result['errorNum'] != 1207) { // Not "duplicate name" error
        die("Failed to create database: " . print_r($result, true));
    }
}

// Switch to the new database
$client->setDatabase($dbName);

// Create collection if it doesn't exist
$result = $client->createCollection($collectionName);
if (isset($result['error']) && $result['error']) {
    echo "Collection creation response: " . PHP_EOL;
    print_r($result);
    if ($result['errorNum'] != 1207) { // Not "duplicate name" error
        die("Failed to create collection: " . print_r($result, true));
    }
}

// Create a new document
$data = array("name" => "John Doe", "age" => 30);
$response = $client->createDocument($collectionName, $data);
echo "Create document response: " . PHP_EOL;
print_r($response);

if (isset($response['error']) && $response['error']) {
    die("Failed to create document: " . print_r($response, true));
}

// Read the document
$id = $response["_id"];
$response = $client->readDocument($collectionName, $id);
echo "Read document response: " . PHP_EOL;
print_r($response);

// Update the document
$data = array("name" => "Jane Doe", "age" => 31);
$response = $client->updateDocument($collectionName, $id, $data);
echo "Update document response: " . PHP_EOL;
print_r($response);

// Read the updated document
$response = $client->readDocument($collectionName, $id);
echo "Read updated document response: " . PHP_EOL;
print_r($response);

// Delete the document
$response = $client->deleteDocument($collectionName, $id);
echo "Delete document response: " . PHP_EOL;
print_r($response);


// require_once "ArangoDBClient.php";

// $client = new ArangoDBClient("localhost", 8529, "root", "zaun3r3", "_system");

// $dbName = "test_database";
// $collectionName = "test_collection";

// // Create database if it doesn't exist
// if (!$client->databaseExists($dbName)) {
//     $result = $client->createDatabase($dbName);
//     if (isset($result['error']) && $result['error']) {
//         die("Failed to create database: " . print_r($result, true));
//     }
// }

// // Switch to the new database
// $client->setDatabase($dbName);

// // Create collection if it doesn't exist
// if (!$client->collectionExists($collectionName)) {
//     $result = $client->createCollection($collectionName);
//     if (isset($result['error']) && $result['error']) {
//         die("Failed to create collection: " . print_r($result, true));
//     }
// }

// // Create a new document
// $data = array("name" => "John Doe", "age" => 30);
// $response = $client->createDocument($collectionName, $data);
// print_r($response);

// // Read the document
// $id = $response["_id"];
// $response = $client->readDocument($collectionName, $id);
// print_r($response);

// // Update the document
// $data = array("name" => "Jane Doe", "age" => 31);
// $response = $client->updateDocument($collectionName, $id, $data);
// print_r($response);

// // Delete the document
// $response = $client->deleteDocument($collectionName, $id);
// print_r($response);
