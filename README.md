DataBean
========
DataBean is a simple and testable database access layer for PHP. It is easy to write tests and create simple beans for accessing and updating the database.

Setup
-----
Setup is simple 

    \BuyPlayTix\DataBean\DB::init(array(
        "user" => $database_username,
        "pass" => $database_password,
        "name" => $database_name,
        "host" => $database_host,
    ));
    // (optional) pass in a logger that implements IDBLogger
    \BuyPlayTix\DataBean\DB::setLogger($logger);


Creating a Bean
---------------
A bean maps directly to a table. Setting up a bean is simple:

    class Message extends \BuyPlayTix\DataBean\DataBean
    {
      protected $table = 'MESSAGES';
      protected $pk = 'UID';
    }

Interacting with a Bean
-----------------------

### Loading a bean ###

    // create a new bean
    $message = new Message();
   
    // load a bean by primary key
    $message = new Message($uid);
  
    // load a bean by a unique key
    $message = new Message(array("SUBJECT", $subject));
  
### Loading multiple beans ###

    // load by a key
    $messages = Message::getObjects("OWNER_UID", $owner_uid);
    
    // load by a key with values
    $messages = Message::getObjects("OWNER_UID", array($owner_uid1, $owner_uid2, $owner_uid3));    
    
    // load by a key with ordering
    $messages = Message::getObjects("OWNER_UID", $owner_uid, " ORDER BY INSERT_TIMESTAMP ");
    
### Updating ###
   
    $message->OWNER_UID = $new_owner_uid;
    $message->update();
    
### Deleting ###

    $message->delete();

### Arbitrary SQL ###
Why would you want to do this rather than directly running against the database? One word - "testing".

    // select
    $messages = raw_select("MESSAGES", array("UID", "SUBJECT", "BODY"), array("OWNER_UID" => $owner_uid));
    
    // update
    raw_update("MESSAGES", array("OWNER_UID" => $new_owner_uid), array("OWNER_UID => $old_owner_uid));
    
    // delete
    raw_delete("MESSAGES", array("OWNER_UID => $owner_uid));
    
    // complex query
    $results = named_query("MESSAGES_BY_HOUR", "select date_format(INSERT_TIMESTAMP, '%H'), count(*) from MESSAGES group by date_format(INSERT_TIMESTAMP, '%H')");
    
Testing
-------

### Setting up a Test ###

    \BuyPlayTix\DataBean\DB::setInstance(\BuyPlayTix\DataBean\NullDB::getInstance());
    \BuyPlayTix\DataBean\DataBean::setAdapter(new \BuyPlayTix\DataBean\ObjectAdapter());

### A Simple Test ###

    $message = new Message();
    $message->SUBJECT = "A Test Message";
    $message->update();
    
    assertEquals(1, Message::getObjects("SUBJECT", array("A Test Message"));
    
### A More Complex Test ###
Let's say that we want to test the method get_queries_by_hour defined like this on our message databean:

    function get_queries_by_hour() {
      return named_query("MESSAGES_BY_HOUR", "select date_format(INSERT_TIMESTAMP, '%H'), count(*) from MESSAGES group by date_format(INSERT_TIMESTAMP, '%H')");
    }
    

    $adapter = \BuyPlayTix\DataBean\DataBean::getAdapter();
    $adapter->set_named_query_value("MESSAGES_BY_HOUR", array(array("1", 200), array("2", 300));
    $results = $message->get_queries_by_hour();
    assertEquals(2, count($results));
    
