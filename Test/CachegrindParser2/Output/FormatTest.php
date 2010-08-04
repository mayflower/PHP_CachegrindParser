<?php

/**
 * This file tests the class CachegrindParser2\Output\Format.
 *
 * PHP version 5
 *
 * @author Thomas Bley <thomas.bley@mayflower.de>
 */

require_once 'CachegrindParser2/Output/Format.php';
require_once 'PHPUnit/Framework.php';

class CachegrindParser2_Output_Format_Test extends PHPUnit_Framework_TestCase
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
        $outputFile = tempnam('/tmp', 'testFormatOutput_') . '.dot';
        $format = new CachegrindParser2_Output_Format($this->_db, $outputFile, 'dot');

        $this->assertEquals('CachegrindParser2_Output_Format', get_class($format));
    }


    /**
     * Tests if the tree is created correctly
     */
    public function testFormat()
    {
        $parser = new CachegrindParser2_Input_Parser( self::$_cachegrindTemplate, $this->_db, 0, true );
        $parser->createTree();

        $outputFile = tempnam('/tmp', 'testFormatOutput_') . '.dot';

        $format = new CachegrindParser2_Output_Format($this->_db, $outputFile, 'dot');
        $format->format();

        $data = file_get_contents($outputFile);

        // test function names
        $this->assertContains('{main}', $data);
        $this->assertContains('test1', $data);
        $this->assertContains('test2', $data);
        $this->assertContains('test3', $data);
        $this->assertContains('test4', $data);
        $this->assertContains('example.php', $data);

        // test node IDs
        $mainPath = md5('{main}');
        $test1Path = md5('{main}##test1');
        $test2Path = md5('{main}##test1##test2');
        $test3Path = md5('{main}##test1##test2##test3');
        $test4Path = md5('{main}##test4');

        $this->assertContains($mainPath, $data);
        $this->assertContains($test1Path, $data);
        $this->assertContains($test2Path, $data);
        $this->assertContains($test3Path, $data);
        $this->assertContains($test4Path, $data);

        $this->assertContains("\"{$mainPath}\" -> \"{$test1Path}\"", $data);
        $this->assertContains("\"{$test1Path}\" -> \"{$test2Path}\"", $data);
        $this->assertContains("\"{$test2Path}\" -> \"{$test3Path}\"", $data);
        $this->assertContains("\"{$mainPath}\" -> \"{$test4Path}\"", $data);

        // TODO test costs
        // TODO test private methods
    }
}
