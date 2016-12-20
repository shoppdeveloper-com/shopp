<?php
/**
 * ShoppSessionTests
 *
 *
 *
 * @version 1.0
 * @copyright Ingenesis Limited, December 2016
 * @package shopp/tests
 **/

/**
 * ShoppSessionTests
 *
 * @author
 * @since 1.3.12
 * @package shopp
 **/
class ShoppSessionTests extends ShoppTestCase {

    private $table = '';
    const SAMPLEDATA = 'Sample data';

	public static function setUpBeforeClass() {
		$db = sDB::object();
    }

    public static function tearDownAfterClass() {
        $table = TestSession::tablename();
        sDB::query("DELETE FROM $table");
    }

    static function resetTests () {
    }

    function setUp () {
        parent::setUp();
        $this->table = TestSession::tablename();
        $this->ip = getHostByName(getHostName());
        self::resetTests();
    }

    /**
     * @covers ShoppSessionFramework::create
     */
    public function test_constructor () {
        $TestSession = new TestSession();

        $total = sDB::query("SELECT count(*) AS sessions FROM $this->table");
        $this->assertEquals(1, $total->sessions);

        $result = sDB::query("SELECT session FROM $this->table");
        $this->assertEquals($TestSession->session, $result->session);
        return $TestSession;
    }

    /**
     * @depends test_constructor
     */
    public function test_session ($TestSession) {
        $this->assertEquals($TestSession->session, $TestSession->session());
    }

    /**
     * @depends test_constructor
     */
    public function test_open ($TestSession) {
        $_SERVER['REMOTE_ADDR'] = $this->ip;
        $open = $this->invokeMethod($TestSession, 'open');
        $this->assertTrue($open);
        $this->assertEquals($this->ip, $TestSession->ip);
    }

    /**
     * @depends test_constructor
     */
    public function test_save ($TestSession) {
        $Object = new StdClass();

        $Object->string = 'value';
        $Object->int = 42;
        $Object->float = pi();
        $Object->bool = true;
        $Object->escaped = "Shopp's";

        $TestSession->data = $Object;

        $saved = $this->invokeMethod($TestSession, 'save');
        $this->assertTrue($saved);

        return $TestSession;
    }

    /**
     * @depends test_save
     */
    public function test_load ($TestSession) {
        $loaded = $this->invokeMethod($TestSession, 'load');
        $this->assertTrue($loaded);
        return $TestSession;
    }

    /**
     * @depends test_constructor
     */
    public function test_cook ($TestSession) {
        $cantcook = function () {
            return false;
        };

        add_filter('shopp_session_cook', $cantcook);
        $notcooked = $this->invokeMethod($TestSession, 'cook');
        $this->assertFalse($notcooked);

        remove_filter('shopp_session_cook', $cantcook);
        $cooked = $this->invokeMethod($TestSession, 'cook');
        $this->assertTrue($cooked);

    }

    /**
     * @depends test_load
     */
    public function test_exists ($TestSession) {
        $notexists = $this->invokeMethod($TestSession, 'exists', array(-123));
        $this->assertFalse($notexists);

        $exists = $this->invokeMethod($TestSession, 'exists', array($TestSession->session));
        $this->assertTrue($exists);
    }


    /**
     * @depends test_load
     */
    public function test_securekey ($TestSession) {
        $_SERVER['HTTPS'] = '1';
        $securekey = $this->invokeMethod($TestSession, 'securekey');
        $this->assertTrue( strlen($securekey) == 64 );
        return $TestSession;
    }

    /**
     * @depends test_securekey
     */
    public function test_secured ($TestSession) {
        $notsecured = $this->invokeMethod($TestSession, 'secured');
        $this->assertFalse($notsecured);

        $secured = $this->invokeMethod($TestSession, 'secured', array(true));
        $this->assertTrue($secured);
    }

    public function test_encrypt () {        
        $_SERVER['HTTPS'] = '1'; // Turn SSL on
        $TestSession = new TestSession();
        $TestSession->secured(true); // Set the session as secured so encryption works
        $TestSession->data = self::SAMPLEDATA; // Set simple sample data to test
        
        
        $data = sDB::escape( addslashes(serialize($TestSession->data)) );
        $this->invokeMethod($TestSession, 'encrypt', array(&$data));
        $this->assertFalse(ctype_print($data), "Encryption test failed detecting binary characters.");
        
        $TestSession->data = $data;
        return $TestSession;
    }
    
    /**
     * @depends test_encrypt
     */
    public function test_decrypt ($TestSession) {
        $data = $TestSession->data;
        
        $this->assertEquals(TestSession::ENCRYPTION, substr($data, 0, strlen(TestSession::ENCRYPTION)), "Could not detect encryption BOF sequence.");
        $this->invokeMethod($TestSession, 'decrypt', array(&$data));
        $unencrypted = unserialize($data);
        $this->assertEquals(self::SAMPLEDATA, $unencrypted, "Decryption test failed to decrypt data.");
    }

    /**
     * @depends test_load
     */
    public function test_destroy ($TestSession) {
        $this->invokeMethod($TestSession, 'destroy');
        $destroyed = array('session', 'ip', 'data', 'created', 'modified');
        foreach ( $destroyed as $property )
            $this->assertTrue(! isset($TestSession->$property));
    }

    /** Stateful tests above this line **/
    public function test_clean () {
        $TestSession = new TestSession();
        
        $modified = sDB::mkdatetime( time() - ( SHOPP_SESSION_TIMEOUT * 2 ) );
        sDB::query("UPDATE $this->table SET modified='" . $modified . "'");
        $results = sDB::query("SELECT * FROM $this->table");

        $TestSession->clean();
        $total = sDB::query("SELECT count(*) AS sessions FROM $this->table");
        $this->assertEquals(0, $total->sessions);
        
        for ( $i = 0; $i < 2; $i++ )
            $TestSession->session(true);

        $total = sDB::query("SELECT count(*) AS sessions FROM $this->table");
        $this->assertEquals(2, $total->sessions);
        
        // Set sample data so clean() only removes expired records
        sDB::query("UPDATE $this->table SET data='sample data'");

        // Expire half of the records
        $modified = sDB::mkdatetime( time() - ( SHOPP_SESSION_TIMEOUT * 2 ) );
        sDB::query("UPDATE $this->table SET modified='" . $modified . "' LIMIT 1");
        
        $TestSession->clean();
        $total = sDB::query("SELECT count(*) AS sessions FROM $this->table");
        $this->assertEquals(1, $total->sessions);
    }
    
    public function test_entropy () {
        $TestSession = new TestSession();
        $salt = $this->invokeMethod($TestSession, 'entropy');

        // Calculate the entropy score
        // 0 is very weak, 1 is weak, 2 is fair, 3 is strong, 4 is very strong,
        // 5 really very strong, 6 is Shopp strong

        $h = 0;
        $size = strlen($salt);
        foreach ( count_chars($salt, 1) as $v ) {
            $p = $v / $size;
            $h -= $p * log($p) / log(2);
        }

        $this->assertFalse( $h < 2, "Session entropy was weak!" );
        $this->assertTrue( $h > 4, "Session entropy was not very strong." );
        $this->assertTrue( $h > 6, "Session entropy was not Shopp strong." );

    }

}

class TestSession extends ShoppSessionFramework {

    public function __construct () {
		// Set the database table to use
		$this->_table = ShoppDatabaseObject::tablename('shopping');

		// Initialize the session handlers
		parent::__construct();
    }

    public static function tablename () {
        return ShoppDatabaseObject::tablename('shopping');
    }

    public function unlock () {
        return true;
    }

}
