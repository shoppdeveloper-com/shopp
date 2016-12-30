<?php
/**
 * ShoppingTests
 *
 * Tests the Shopping class
 *
 * @version 1.0
 * @copyright Ingenesis Limited, December 2016
 * @package shopp/tests
 **/

/**
 * ShoppingTests
 *
 * @author
 * @since 1.3.12
 * @package shopp
 **/
class ShoppingTests extends ShoppTestCase {

    private $table = '';
    const SAMPLEDATA = 'Sample data';

	public static function setUpBeforeClass() {
        $_SERVER['HTTP_USER_AGENT'] = "Shopp Unit Tests";
		$db = sDB::object();
    }

    public static function tearDownAfterClass() {
        $table = ShoppDatabaseObject::tablename('shopping');
        sDB::query("DELETE FROM $table");
    }

    static function resetTests () {
    }

    function setUp () {
        parent::setUp();        
        $this->table = ShoppDatabaseObject::tablename('shopping');
        self::resetTests();
    }

    public function test_constructor () {
        $Shopping = new Shopping();
        $total = sDB::query("SELECT count(*) AS sessions FROM $this->table");
        $this->assertEquals(1, $total->sessions);

        $result = sDB::query("SELECT session FROM $this->table");
        $this->assertEquals($Shopping->session, $result->session);

        return $Shopping;
    }
    
    public function test_object () {
        $Shopping = Shopping::object();
        $this->assertInstanceOf('Shopping', $Shopping);
    }
    
    public function test_reset () {
        $Shopping = new Shopping();
        $Shopping->data = self::SAMPLEDATA;
        $original = $Shopping->session;

        $Shopping->reset();
        
        $this->assertEmpty($Shopping->data);
        $this->assertNotEquals($original, $Shopping->session);
    }

    public function test_reprovision () {
        $Shopping = new Shopping();
        $Shopping->data = self::SAMPLEDATA;
        $original = $Shopping->session;

        $Shopping->reprovision();
        
        $this->assertEquals(self::SAMPLEDATA, $Shopping->data);
        $this->assertNotEquals($original, $Shopping->session);
    }

    public function test_preload () {
        
        $NewShopping = new Shopping();
        $NewShopping->data = self::SAMPLEDATA;
        $NewShopping->save();

        $total = sDB::query("SELECT count(*) AS found FROM $this->table WHERE session='$NewShopping->session'");
        $this->assertEquals(1, $total->found);
        
        $TestShopping = new Shopping();
        $TestShopping->session(true);
        $TestShopping->data = "Other Data";
        
        $TestShopping->preload($NewShopping->session);
        $this->assertEquals($NewShopping->data, $TestShopping->data);
        
    }

    public function test_resession () {
        $Shopping = ShoppShopping();
        $session = $Shopping->session;
        $backup = $Shopping->data;
        
        $NewShopping = new Shopping();
        $NewShopping->data = new StdClass();
        $NewShopping->data->test = self::SAMPLEDATA;
        $NewShopping->save();

        $total = sDB::query("SELECT count(*) AS found FROM $this->table WHERE session='$NewShopping->session'");
        $this->assertEquals(1, $total->found);
        
        Shopping::resession($NewShopping->session);
        
        $this->assertEquals(self::SAMPLEDATA, $Shopping->data->test);

        Shopping::resession();
        $this->assertNotEquals($session, $Shopping->session);
        
        $Shopping->data = $backup;
    }
    
    public function test_cookable () {
        $Shopping = new Shopping();
        
        $cantcook = function () {
            return false;
        };

        add_filter('shopp_session_cook', $cantcook);
        $this->assertFalse($Shopping->cookable());

        remove_filter('shopp_session_cook', $cantcook);
        $this->assertTrue($Shopping->cookable());
        
        $_SERVER['HTTP_X_MOZ'] = 'prefetch';
        $this->assertFalse($Shopping->cookable());
        
        unset($_SERVER['HTTP_X_MOZ']);
        $this->assertTrue($Shopping->cookable());
        
        $_SERVER['HTTP_X_PURPOSE'] = 'preview';
        $this->assertFalse($Shopping->cookable());

        unset($_SERVER['HTTP_X_PURPOSE']);
        $this->assertTrue($Shopping->cookable());

        $_SERVER['HTTP_X_PURPOSE'] = 'instant';
        $this->assertFalse($Shopping->cookable());

        unset($_SERVER['HTTP_X_PURPOSE']);
        $this->assertTrue($Shopping->cookable());
        
    }
    
    /**
     * Tests restarting objects from a session
     */
    public function test_restart () {
        $Shopping = ShoppShopping();
        $original = $Shopping->session;
        
        // Start a new, blank session
        $Shopping->reprovision(); 
        $Shopping->data = new StdClass();

        // Start up the restart test object
        // ShoppingRestartTestObject acts as a container/controller for the 
        // data object, ShoppingRestartTestDataObject
        // Shopping::restart() is called in ShoppingRestartTestObject and
        // binds ShoppingRestartTestDataObject to the session
        $UnmutatedRestartTestObject = new ShoppingRestartTestObject();
        $Shopping->save(); // Save the changes to the session
        $unmutated = $Shopping->session; // Keep track of the session id
        
        // Start another new, blank session
        $Shopping->reprovision();
        $Shopping->data = new StdClass();
        // Create a separate restart test object that is changed so we can compare
        $MutatedRestartTestObject = new ShoppingRestartTestObject();
        $MutatedRestartTestObject->mutate(); // Here's the change
        $Shopping->save(); // Save the changes
        $mutated = $Shopping->session; // Track the session id
        
        // Start a blank session and load the unmutated test data
        $Shopping->reprovision();
        $Shopping->preload($unmutated);
        // Restart the container object
        $TestUnmutated = new ShoppingRestartTestObject();
        // Check that the test data object's data value is 'false' as expected
        $this->assertFalse($TestUnmutated->TestData->data);

        // Start a blank session and load the mutated test data
        $Shopping->reprovision();
        $Shopping->preload($mutated);
        // Restart the container object
        $TestMutated = new ShoppingRestartTestObject();
        // Prove that the changes to the object were restored 
        // when the container object restarted the test data object instance from the session
        $this->assertTrue($TestMutated->TestData->data);
        
        // Restore the origional Shopping session state
        $Shopping->preload($original);
        $Shopping->session = $original;
    }
    
    public function test_restore () {
        $Shopping = ShoppShopping();
        $original = $Shopping->session;
        
        // Start a new, blank session
        $Shopping->reprovision(); 
        $Shopping->data = new StdClass();

        // Start up the restart test object
        // This time the ShoppingRestartTestObject acts as a container/controller
        // for a single property
        // Shopping::restore() is called in ShoppingRestartTestObject and
        // binds the property value to a named property in the session data
        $UnmutatedRestartTestObject = new ShoppingRestartTestObject();
        $Shopping->save(); // Save the changes to the session
        $unmutated = $Shopping->session; // Keep track of the session id
        
        // Start another new, blank session
        $Shopping->reprovision();
        $Shopping->data = new StdClass();
        // Create a separate restart test object that is changed so we can compare
        $MutatedRestartTestObject = new ShoppingRestartTestObject();
        $MutatedRestartTestObject->property = true; // Here's the change
        $Shopping->save(); // Save the changes
        $mutated = $Shopping->session; // Track the session id
        
        // Start a blank session and load the unmutated test data
        $Shopping->reprovision();
        $Shopping->preload($unmutated);
        // Restart the container object
        $TestUnmutated = new ShoppingRestartTestObject();
        // Check that the test data object's data value is 'false' as expected
        $this->assertFalse($TestUnmutated->property);

        // Start a blank session and load the mutated test data
        $Shopping->reprovision();
        $Shopping->preload($mutated);
        // Restart the container object
        $TestMutated = new ShoppingRestartTestObject();
        // Prove that the changes to the object were restored 
        // when the container object restarted the test data object instance from the session
        $this->assertTrue($TestMutated->property);
        
        // Restore the origional Shopping session state
        $Shopping->preload($original);
        $Shopping->session = $original;

    }
    
    /**
     * Tests sessions are and are not created under different requests as expected
     * and prevents runaway session creation
     */
    public function test_session_creation () {
        
        // Ensure there is no session data to start
        $table = ShoppDatabaseObject::tablename('shopping');
        sDB::query("DELETE FROM $table");

        $Shopping = new Shopping();
        $total = sDB::query("SELECT count(*) AS sessions FROM $this->table");
        $this->assertEquals(1, $total->sessions);

        $Shopping->reprovision();
        $Shopping->save();
        $total = sDB::query("SELECT count(*) AS sessions FROM $this->table");
        $this->assertEquals(2, $total->sessions);

        $_SERVER['HTTP_X_MOZ'] = 'prefetch';
        $Shopping->reprovision();
        $Shopping->save();
        $total = sDB::query("SELECT count(*) AS sessions FROM $this->table");
        $this->assertEquals(2, $total->sessions);
        unset($_SERVER['HTTP_X_MOZ']);

        $_SERVER['HTTP_X_PURPOSE'] = 'preview';
        $Shopping->reprovision();
        $Shopping->save();
        $total = sDB::query("SELECT count(*) AS sessions FROM $this->table");
        $this->assertEquals(2, $total->sessions);

        $_SERVER['HTTP_X_PURPOSE'] = 'instant';
        $Shopping->reprovision();
        $Shopping->save();
        $total = sDB::query("SELECT count(*) AS sessions FROM $this->table");
        $this->assertEquals(2, $total->sessions);

        $_SERVER['HTTP_X_PURPOSE'] = 'other';
        $Shopping->reprovision();
        $Shopping->save();
        $total = sDB::query("SELECT count(*) AS sessions FROM $this->table");
        $this->assertEquals(3, $total->sessions);
        unset($_SERVER['HTTP_X_PURPOSE']);

        $cantcook = function () {
            return false;
        };

        add_filter('shopp_session_cook', $cantcook);
        $Shopping->reprovision();
        $Shopping->save();
        $total = sDB::query("SELECT count(*) AS sessions FROM $this->table");
        $this->assertEquals(3, $total->sessions);
        
        remove_filter('shopp_session_cook', $cantcook);
        $Shopping->reprovision();
        $Shopping->save();
        $total = sDB::query("SELECT count(*) AS sessions FROM $this->table");
        $this->assertEquals(4, $total->sessions);
        
    }
    
}

class ShoppingRestartTestObject {
    
    public $TestData = false;
    public $property = false;
    
    function __construct () {
        $this->TestData = Shopping::restart('ShoppingRestartTestDataObject');
        Shopping::restore('test_property', $this->property);
    }
    
    function mutate () {
        $this->TestData->data = true;
    }
    
}

class ShoppingRestartTestDataObject {

    public $data = false;
    
}