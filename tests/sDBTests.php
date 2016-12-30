<?php
/**
 * sDBTests
 *
 * Tests the sDB class and sub-systems
 *
 * @version 1.0
 * @copyright Ingenesis Limited, December 2016
 * @package shopp/tests
 **/

/**
 * sDBTests
 *
 * @author
 * @since 1.3.12
 * @package shopp
 **/
class sDBTests extends ShoppTestCase {
    
    public function test_object () {
        $Object = sDB::object();
        $this->assertInstanceOf('sDB', $Object);
    }

    public function test_reconnect () {
        
        $tables = sDB::query('SHOW TABLES');
        $this->assertGreaterThan(0, count($tables));

        $sDB = sDB::object();
        $sDB->reconnect();
        
        $tables = sDB::query('SHOW TABLES');
        $this->assertGreaterThan(0, count($tables));
                
    }
    
    public function test_hastable () {    
        $sDB = sDB::object();
        
        $shopping_table = ShoppDatabaseObject::tablename('shopping');
        $this->assertTrue($sDB->hastable($shopping_table));

        $random_table = ShoppDatabaseObject::tablename('some_other_random_table');
        $this->assertFalse($sDB->hastable($random_table));
    }
    
    public function test_mktime () {
        $pass = array(
            '1970-01-01 00:00:00' => 0,
            '1970-1-1 0:0:1' => 1,
            '2000-01-01 12:00:00' => 946728000,
            '2008-12-30 23:59:59' => 1230681599,
            '2050-08-30 12:34:56' => 2545475696,
        );
        
        foreach ( $pass as $data => $expected) {
            $this->assertEquals($expected, sDB::mktime($data));
        }
        
        $fail = array(
            '0000-00-00 00:00:00' => 0,            
            '0' => 0,
            '00:00:00 1978-08-30' => 0,
        );
        
        foreach ( $fail as $data => $expected) {
            $this->assertEquals($expected, sDB::mktime($data));
        }
    }
    
    public function test_mkdatetime () {
        $pass = array(
            '1970-01-01 00:00:00' => 0,
            '1970-01-01 00:00:01' => 1,
            '2000-01-01 12:00:00' => 946728000,
            '2008-12-30 23:59:59' => 1230681599,
            '2050-08-30 12:34:56' => 2545475696,
        );
        
        $pass = array_flip($pass);
        foreach ( $pass as $data => $expected) {
            $this->assertEquals($expected, sDB::mkdatetime($data));
        }    
        
    }


    
    // @todo mktime
    // @todo mkdatetime
    // @todo escape
    // @todo unescape
    // @todo serialized
    // @todo clean
    // @todo caller
    // @todo query
    // @todo select
    // @todo found
    // @todo datatype
    // @todo prepare
    // @todo column_options
    // @todo loaddata
    // @todo auto
    // @todo index
    // @todo col

}