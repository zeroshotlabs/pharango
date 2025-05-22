<?php
namespace arangodb;

require __DIR__ . "/init.php";

// User class example
class User extends AbstractEntity
{
    /**
     * Collection name.
     *
     * @var string
     */
    protected $_collectionName = "users";

    public function setName($value)
    {
        $this->set("name", trim($value));
    }

    public function setAge($value)
    {
        $this->set("age", (int) $value);
    }

    public function onCreate()
    {
        parent::onCreate();

        $this->set("_dateCreated", date("Y-m-d H:i:s"));
    }

    public function onUpdate()
    {
        parent::onUpdate();
        $this->set("_dateUpdated", date("Y-m-d H:i:s"));
    }
}

// Users collection example
class Users extends AbstractCollection
{
    protected $_documentClass = "\arangodb\User";
    protected $_collectionName = "users";

    public function getByAge($value)
    {
        return $this->findByExample(["age" => $value])->getAll();
    }
}

try {
    $connection = new Connection($connectionOptions);
    $usersCollection = new Users($connection);

    // set up a document collection "users"
    $collection = new Collection("users");
    try {
        $usersCollection->create($collection);
    } catch (\Exception $e) {
        // collection may already exist - ignore this error for now
        echo "Collection may already exist: " . $e->getMessage() . PHP_EOL;
    }

    // create a new document
    $user1 = new User();
    $user1->setName("  John  ");
    $user1->setAge(19);
    $usersCollection->store($user1);
    var_dump($user1);

    $user2 = new User();
    $user2->setName("Marry");
    $user2->setAge(19);
    $usersCollection->store($user2);
    var_dump(json_encode($user2));

    // get document by example
    $cursor = $usersCollection->findOneByExample(["age" => 19, "name" => "John"]);
    var_dump($cursor);

    // get cursor by example
    $cursor = $usersCollection->findByExample(["age" => 19]);
    var_dump($cursor->getAll());

    $array = $usersCollection->getByAge(19);
    var_dump($array);

} catch (ConnectException $e) {
    print $e . PHP_EOL;
} catch (ServerException $e) {
    print $e . PHP_EOL;
} catch (ClientException $e) {
    print $e . PHP_EOL;
}


$db->createDatabase("proxee");
$db->createCollection("proxee");


$r = $db->createDocument("proxee", [
    "name" => "John Doe",
    "age" => 30,
    "email" => "john.doe@example.com"
]);

$ff = $db->getDocument("proxee", $r["_id"]);

$db->close();