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
    private static $_cachegrindTemplate =
        'Examples/example.cachegrind.out.dump';


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

        $this->_db->exec(
        "CREATE TABLE node (
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
        )"
        );

        $this->assertEquals('PDO', get_class($this->_db));
    }


    /**
     * TearDown
     */
    protected function tearDown()
    {
        $this->_db = NULL;
    }


    /**
     * Tests if the constructor is working
     */
    public function testConstructor()
    {
        $parser = new CachegrindParser2_Input_Parser(
                    self::$_cachegrindTemplate, $this->_db, 0, true
                  );

        $this->assertEquals(
            'CachegrindParser2_Input_Parser',
            get_class($parser)
        );
    }


    /**
     * Tests if the tree is created correctly
     */
    public function testCreateTree()
    {
        $parser = new CachegrindParser2_Input_Parser(
            self::$_cachegrindTemplate, $this->_db, 0, true
        );

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

        $this->assertEquals(
            'CachegrindParser2_Input_Parser',
            get_class($parser)
        );
    }


    /**
     * Tests if blocks are parsed correctly
     */
    public function testParseRecordNode()
    {
        // cachegrind block example,
        $block = preg_replace(
        "/\n\\s+/", "\n", trim('
            fl=/home/data/www/htdocs/example.php
            fn=test2
            7 141 120 0 0
            cfn=test3
            calls=1 0 0
            11 16 40 0 0
            cfn=test3
            calls=1 0 0
            12 7 0 0 0
        ')
        );

        $resultExpected = array (
            'filename' => '/home/data/www/htdocs/example.php',
            'function_name' => 'test2',
            'id' => '7',
            'path' => 'undef',
            'part' => 0,
            'count' => 1,
        );

        $parser = new CachegrindParser2_Input_Parser(
            self::$_cachegrindTemplate, $this->_db, 0, true
        );

        $this->assertMethodReturnEqual(
            array($parser, '_parseRecordNode'),
            array($block, 0), $resultExpected
        );
    }


    /**
     * Tests if summaries are correctly parsed
     */
    public function testGetSummaries()
    {
        $resultExpected = array (
            'cost_time' => 1001,
            'cost_cycles' => 0,
            'cost_memory' => '344',
            'cost_memory_peak' => '0',
        );

        $parser = new CachegrindParser2_Input_Parser(
            self::$_cachegrindTemplate, $this->_db, 0, true
        );

        $this->assertMethodReturnEqual(
            array($parser, '_getSummaries'),
            array(self::$_cachegrindTemplate), $resultExpected
        );
    }


    /**
     * Tests if subCalls are processed correctly
     */
    public function testParseSubCalls()
    {
        // cachegrind block example,
        $block = preg_replace(
        "/\n\\s+/", "\n", trim('
            fl=/home/data/www/htdocs/example.php
            fn=test2
            7 141 120 0 0
            cfn=test3
            calls=1 0 0
            11 16 40 0 0
            cfn=test3
            calls=1 0 0
            12 7 0 0 0
        ')
        );

        $nodePath = '{main}##test1';

        $rootCosts = array(
            'cost_time' => 1001,
            'cost_cycles' => 0,
            'cost_memory' => 344,
            'cost_memory_peak' => 0,
        );

        $expectedResultRefs = array(
             '11test3' => '{main}##test1##test3',
            'test3' => '{main}##test1##test3',
            '12test3' => '{main}##test1##test3'
        );

        $expectedResultCounts = array(
            'test3' => 1
        );

        $parser = new CachegrindParser2_Input_Parser(
            self::$_cachegrindTemplate, $this->_db, 0, true
        );

        $this->assertMethodReturnEqual(
            array($parser, '_parseSubCalls'),
            array($nodePath, $block, $rootCosts), null
        );

        $this->assertAttributeEquals(
            $expectedResultRefs, '_subCallRefs', $parser
        );

        $this->assertAttributeEquals(
            $expectedResultCounts, '_subCallCounts', $parser
        );
    }


    /**
     * Tests if costs are parsed correctly
     */
    public function testParseCosts()
    {
        // cachegrind block example,
        $block = preg_replace(
        "/\n\\s+/", "\n", trim('
            fl=/home/data/www/htdocs/example.php
            fn=test2
            7 141 120 1 0
            cfn=test3
            calls=1 0 0
            11 16 40 2 3
            cfn=test3
            calls=1 4 5
            12 7 9 7 8
        ')
        );

        $expectedResults = array(
            'cost_time' => 164,
            'cost_cycles' => 10,
            'cost_memory' => 120,
            'cost_memory_peak' => 8,
            'cost_time_self' => 141,
            'cost_cycles_self' => 1,
            'cost_memory_self' => 120,
            'cost_memory_peak_self' => 0
        );

        $parser = new CachegrindParser2_Input_Parser(
            self::$_cachegrindTemplate, $this->_db, 0, true
        );

        $this->assertMethodReturnEqual(
            array($parser, '_parseCosts'), array($block), $expectedResults
        );
    }


    /**
     * Tests if functions are filtered  correctly
     */
    public function testFilter()
    {
        $rootCosts = array (
            'cost_time' => 1001,
            'cost_cycles' => 0,
            'cost_memory' => 344,
            'cost_memory_peak' => 0,
        );

        $recordNode = array(
            'cost_time' => 100
        );

        $recordNodeLow = array(
            'cost_time' => 10
        );

        $parser = new CachegrindParser2_Input_Parser(
            self::$_cachegrindTemplate, $this->_db, 0.01, true
        );

        $this->assertMethodReturnEqual(
            array($parser, '_filter'),
            array('php::str_replace', $rootCosts, $recordNode, ''), true
        );

        $this->assertMethodReturnEqual(
            array($parser, '_filter'),
            array('php::call_user_func', $rootCosts, $recordNode, ''), false
        );

        $this->assertMethodReturnEqual(
            array($parser, '_filter'),
            array('blabla', $rootCosts, $recordNode, ''), false
        );

        $this->assertMethodReturnEqual(
            array($parser, '_filter'),
            array('blabla2', $rootCosts, $recordNodeLow, ''), true
        );
    }


    /**
     * Tests if the tree is created correctly
     */
    public function testCreateTreeThreshold()
    {
        // get all nodes with at least 1 percent of total cost_time
        $parser = new CachegrindParser2_Input_Parser(
            self::$_cachegrindTemplate, $this->_db, 0.01, true
        );

        $parser->createTree();

        $sql = "
            SELECT * FROM node
            ORDER by part, id
        ";
        $rows = $this->_db->query($sql)->fetchAll();

        $this->assertEquals(7, count($rows));
    }


    /**
     * Helper methods to test private methods
     *
     * @param array $function Array(object class, function)
     * @param array $args Method parameters
     * @param mixed $expected Expected result
     */
    public function assertMethodReturnEqual(
        array $function, $parameters, $expected)
    {
        $method = new ReflectionMethod($function[0], $function[1]);
        $method->setAccessible(TRUE);

        $actual = $method->invokeArgs($function[0], $parameters);

        $this->assertEquals($expected, $actual);
    }
}