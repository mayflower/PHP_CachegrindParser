<?php

/**
 * This file tests the class CachegrindParser2\Input\Parser.
 *
 * PHP version 5
 *
 * @author Thomas Bley <thomas.bley@mayflower.de>
 */

require_once 'CachegrindParser2/Input/Parser.php';
require_once 'PHPUnit/Framework.php';

class CachegrindParser2_Input_Parser_Test extends PHPUnit_Framework_TestCase
{

	/**
	 * Example cachegrind output profile
	 * @var string
	 */
	private static $_cachegrindTemplate = 'Examples/example.cachegrind.out.dump';


	/**
	 * Database handle (PDO)
	 *
	 * @var object
	 */
	private $_db = null;


	/**
	 * initialize the db (PDO_SQLite)
	 */
	protected function setUp()
    {
    	$tmpFile = tempnam('/tmp', 'sqlite_') . '.sqlite';
		$this->_db = new PDO('sqlite:' . $tmpFile);

		$this->_db->exec("CREATE TABLE node (
			part int,
			request varchar,
			filename varchar,
			function_name varchar,
			count int,
			id int,
			cost_time int,
			cost_cycles int,
			cost_memory int,
			cost_memory_peak int,
			cost_time_self int,
			cost_cycles_self int,
			cost_memory_self int,
			cost_memory_peak_self int,
			path varchar
		)");

		$this->assertEquals('PDO', get_class($this->_db));
    }


    /**
     * TearDown
     */
	protected function tearDown()
    {
    }


    /**
     * Tests if the constructor is working
     */
    public function testConstructor()
    {
    	$parser = new CachegrindParser2_Input_Parser( self::$_cachegrindTemplate, $this->_db, 0, true );

		$this->assertEquals('CachegrindParser2_Input_Parser', get_class($parser));
    }


    /**
     * Tests if the tree is created correctly
     */
    public function testCreateTree()
    {
    	$parser = new CachegrindParser2_Input_Parser( self::$_cachegrindTemplate, $this->_db, 0, true );
    	$parser->createTree();


		$sql = "
			SELECT * FROM node
			ORDER by part, id
		";
		$rows = $this->_db->query($sql)->fetchAll();

		$this->assertEquals(13, count($rows));

		$this->assertEquals('{main}', $rows[0]['function_name']);
		$this->assertEquals('{main}', $rows[0]['path']);

		$this->assertEquals('424', $rows[0]['cost_time_self']);
		$this->assertEquals('0', $rows[0]['cost_cycles_self']);
		$this->assertEquals('64', $rows[0]['cost_memory_self']);
		$this->assertEquals('0', $rows[0]['cost_memory_peak_self']);

		$this->assertEquals('1000', $rows[0]['cost_time']);
		$this->assertEquals('0', $rows[0]['cost_cycles']);
		$this->assertEquals('280', $rows[0]['cost_memory']);
		$this->assertEquals('0', $rows[0]['cost_memory_peak']);

		$this->assertEquals('example.php', $rows[0]['request']);

		$this->assertEquals('test3', $rows[7]['function_name']);
		$this->assertEquals('{main}##test1##test2##test3', $rows[7]['path']);
		$this->assertEquals('14', $rows[7]['id']);
		$this->assertEquals('1', $rows[7]['count']);
		$this->assertEquals('0', $rows[7]['part']);


		$this->assertEquals('test3', $rows[4]['function_name']);
		$this->assertEquals('{main}##test1##test2##test3', $rows[4]['path']);
		$this->assertEquals('11', $rows[4]['id']);
		$this->assertEquals('1', $rows[4]['count']);

		$this->assertEquals('21', $rows[4]['cost_time']);
		$this->assertEquals('0', $rows[4]['cost_cycles']);
		$this->assertEquals('40', $rows[4]['cost_memory']);
		$this->assertEquals('0', $rows[4]['cost_memory_peak']);

		$this->assertEquals('21', $rows[4]['cost_time_self']);
		$this->assertEquals('0', $rows[4]['cost_cycles_self']);
		$this->assertEquals('40', $rows[4]['cost_memory_self']);
		$this->assertEquals('0', $rows[4]['cost_memory_peak_self']);

		$this->assertEquals('CachegrindParser2_Input_Parser', get_class($parser));
    }


    /**
     * Tests if the tree is created correctly
     */
    public function testCreateTreeThreshold()
    {
    	// get all nodes with at least 1 percent of total cost_time
    	$parser = new CachegrindParser2_Input_Parser( self::$_cachegrindTemplate, $this->_db, 0.01, true );
    	$parser->createTree();

		$sql = "
			SELECT * FROM node
			ORDER by part, id
		";
		$rows = $this->_db->query($sql)->fetchAll();

		$this->assertEquals(7, count($rows));
    }
}
