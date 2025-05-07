<?php declare(strict_types=1);

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
//     'password' => 'zaun3r3',
//     'database' => 'hoppr'
// ]);


// Exception class for server errors
class ServerException extends \Exception {
    protected int $httpCode;
    
    public function __construct(string $message, int $httpCode = 500) {
        parent::__construct($message);
        $this->httpCode = $httpCode;
    }
    
    public function getHttpCode(): int {
        return $this->httpCode;
    }
}

class ClientException extends exception {}

class ConnectException extends exception {}

// Include required files
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/document.php';
require_once __DIR__ . '/collection.php';
require_once __DIR__ . '/cursor.php';

// Simple test if this file is run directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    try {
        // Create connection
        $connection = new Connection([
            'host' => 'localhost',
            'port' => 8529,
            'username' => 'root',
            'password' => 'zaun3r3',
            'database' => '_system'  // Start with _system database
        ]);

        // Ensure test database exists
        $testDb = 'test';
        echo "Ensuring database '{$testDb}' exists...\n";
        $connection->ensureDatabase($testDb);
        echo "Database '{$testDb}' is ready.\n";

        // Switch to test database
        $connection->useDatabase($testDb);

        // Create collection
        $collection = new Collection('users', $connection);
        echo "Ensuring collection 'users' exists...\n";
        $collection->ensureCollection();
        echo "Collection 'users' is ready.\n";

        // Create a new document
        $user = $collection->create([
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);
        echo "Created user with key: " . $user['_key'] . "\n";

        // Update a field
        $user['age'] = 30;
        echo "Updated age to: " . $user['age'] . "\n";

        // Find specific user
        $john = $collection->readOneByExample(['name' => 'John Doe']);
        if ($john) {
            echo "Found John: " . $john['email'] . " age: " . $john['age'] . "\n";
        }

        // Delete user
        $collection->delete($user['_key']);
        echo "Deleted user\n";

    } catch (ServerException $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

// if (isset($_GET['test']) && $_GET['test'] === 'document') {

// try {
//     // Connect to ArangoDB
//     $connection = new Connection([
//         'host' => 'localhost',
//         'port' => 8529,
//         'username' => 'root',
//         'password' => 'zaun3r3',
//         'database' => '_system'
//     ]);

//     // Ensure the test database exists
//     echo "Ensuring database 'test' exists...\n";
//     $connection->ensureDatabase('test');
    
//     // Switch to the test database
//     $connection->useDatabase('test');
//     echo "Database 'test' is ready.\n";

//     // Create a collection
//     echo "Ensuring collection 'users' exists...\n";
//     $users = new Collection('users', $connection);
//     $users->ensureCollection();
//     echo "Collection 'users' is ready.\n";

//     // Create a document
//     $user = $users->create([
//         'name' => 'John Doe',
//         'email' => 'john@example.com',
//         'age' => 30
//     ]);
//     echo "Created user with key: " . $user->getKey() . "\n";

//     // Update a document
//     $user['age'] = 31;
//     echo "Updated age to: " . $user['age'] . "\n";

//     // Fetch and verify the update
//     $fetchedUser = $users->get($user->getKey());
//     echo "Fetched user age: " . $fetchedUser['age'] . "\n";

//     // Demonstrate reading by example
//     $johnUser = $users->readOneByExample(['name' => 'John Doe']);
//     if ($johnUser) {
//         echo "Found user by example: " . $johnUser['name'] . "\n";
//     }

//     // List all keys in the collection
//     $keys = $users->getKeys();
//     echo "Collection keys: " . implode(', ', $keys) . "\n";

//     // Delete the document
//     $users->delete($user->getKey());
//     echo "Deleted user\n";

//     // Demonstrate cursor usage with a query
//     $cursor = $users->query('FOR u IN users RETURN u');
//     echo "Total documents: " . $cursor->count() . "\n";

//     foreach ($cursor as $doc) {
//         echo "Document: " . $doc['name'] . "\n";
//     }

//     // Clean up - truncate the collection
//     $users->truncate();
//     echo "Collection truncated\n";

// } catch (\Exception $e) {
//     echo "Error: " . $e->getMessage() . "\n";
// }
// }



// // Test the new Document functionality
// if (isset($_GET['test']) && $_GET['test'] === 'document') {
//     try {
//         // Create a connection with default settings
//         $connection = new Connection([
//             'host' => 'localhost',
//             'port' => 8529,
//             'username' => 'root',
//             'password' => 'zaun3r3',
//             'database' => '_system'  // Start with _system database
//         ]);
        
//         // Ensure test database exists
//         $testDb = 'test';
//         echo "Ensuring database '{$testDb}' exists...<br>";
//         $connection->ensureDatabase($testDb);
//         echo "Database '{$testDb}' is ready.<br><br>";
        
//         // Switch to test database
//         $connection->useDatabase($testDb);
        
//         // Create a test collection
//         $collection = new Collection('test_collection', $connection);
//         echo "Ensuring collection 'test_collection' exists...<br>";
//         $collection->ensureCollection();
//         echo "Collection 'test_collection' is ready.<br><br>";
        
//         echo "<h2>Testing Document Operations</h2>";
        
//         // Test 1: Create a new document
//         echo "<h3>Test 1: Create Document</h3>";
//         $doc = new Document([
//             'name' => 'John Doe',
//             'age' => 30,
//             'email' => 'john@example.com'
//         ], $collection);
//         $doc->save();
//         $docKey = $doc['_key'];
//         echo "Created document with key: " . $docKey . "<br>";
//         echo "Document data: " . json_encode($doc->getAll()) . "<br><br>";
        
//         // Test 2: Read document
//         echo "<h3>Test 2: Read Document</h3>";
//         $readDoc = $collection->get($docKey);
//         echo "Read document: " . json_encode($readDoc->getAll()) . "<br><br>";
        
//         // Test 3: Update document using array access
//         echo "<h3>Test 3: Update Document (Array Access)</h3>";
//         $doc['age'] = 31;
//         $doc['city'] = 'New York';
//         echo "Updated document: " . json_encode($doc->getAll()) . "<br><br>";
        
//         // Test 4: Update document using set()
//         echo "<h3>Test 4: Update Document (set method)</h3>";
//         $doc->set('email', 'john.doe@example.com');
//         $doc->set('phone', '123-456-7890');
//         echo "Updated document: " . json_encode($doc->getAll()) . "<br><br>";
        
//         // Test 5: Remove fields
//         echo "<h3>Test 5: Remove Fields</h3>";
//         unset($doc['phone']);
//         $doc->remove('city');
//         echo "Document after removals: " . json_encode($doc->getAll()) . "<br><br>";
        
//         // Test 6: Batch update
//         echo "<h3>Test 6: Batch Update</h3>";
//         $doc->update([
//             'name' => 'John A. Doe',
//             'age' => 32,
//             'title' => 'Developer'
//         ]);
//         echo "Document after batch update: " . json_encode($doc->getAll()) . "<br><br>";
        
//         // Test 7: Field existence checks
//         echo "<h3>Test 7: Field Existence Checks</h3>";
//         echo "Has 'name': " . ($doc->has('name') ? 'Yes' : 'No') . "<br>";
//         echo "Has 'phone': " . ($doc->has('phone') ? 'Yes' : 'No') . "<br>";
//         echo "Has 'title': " . ($doc->has('title') ? 'Yes' : 'No') . "<br><br>";
        
//         // Test 8: Get specific fields
//         echo "<h3>Test 8: Get Specific Fields</h3>";
//         echo "Name: " . $doc->get('name') . "<br>";
//         echo "Age: " . $doc->get('age') . "<br>";
//         echo "Non-existent field: " . ($doc->get('nonexistent') ?? 'null') . "<br><br>";
        
//         // Test 9: Document metadata
//         echo "<h3>Test 9: Document Metadata</h3>";
//         echo "ID: " . $doc['_id'] . "<br>";
//         echo "Key: " . $doc['_key'] . "<br>";
//         echo "Revision: " . $doc['_rev'] . "<br><br>";
        
//         // Test 10: Create another document and test collection operations
//         echo "<h3>Test 10: Multiple Documents</h3>";
//         $doc2 = new Document([
//             'name' => 'Jane Smith',
//             'age' => 28,
//             'email' => 'jane@example.com'
//         ], $collection);
//         $doc2->save();
        
//         // Get all documents
//         $allDocs = $collection->all();
//         echo "All documents in collection:<br>";
//         foreach ($allDocs as $d) {
//             echo json_encode($d->getAll()) . "<br>";
//         }
//         echo "<br>";
        
//         // Test 11: Delete document
//         echo "<h3>Test 11: Delete Document</h3>";
//         $collection->delete($docKey);
//         echo "Deleted document with key: " . $docKey . "<br>";
        
//         // Verify deletion
//         try {
//             $collection->get($docKey);
//             echo "Error: Document still exists!<br>";
//         } catch (\RuntimeException $e) {
//             echo "Success: Document was deleted<br>";
//         }
        
//         echo "<br>All tests completed successfully!";
        
//     } catch (\Exception $e) {
//         echo "Error: " . $e->getMessage() . "<br>";
//         echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre>";
//     }
//     exit;
// }


