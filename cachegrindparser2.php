<?php
/**
 * This file contains the PhpCachegrindParser2
 *
 * The goal of PhpCachegrindParser2 is to do the parsing more efficiently
 *
 * For an easy example of a cachegrind profile output and the corresponding
 * php file, see ./Examples/example.*
 *
 * PHP version 5
 *
 * @author Thomas Bley <thomas.bley@mayflower.de>
 * @version 2.0
 */

error_reporting(E_ALL);

set_include_path(basename(__FILE__) . PATH_SEPARATOR . get_include_path());

define('VERSION', '2.0');

//ini_set( 'memory_limit', '32M' );

require 'CachegrindParser2/Input/Parameters.php';
require 'CachegrindParser2/Input/Parser.php';
require 'CachegrindParser2/Output/Format.php';


// 1. Get the in-/output file and the desired formatting from the command line
$parameters = new CachegrindParser2_Input_Parameters();
$parameters = $parameters->getParameters();


// 2. Initialize database
$sqliteFile = 'database.sqlite';
if (!empty($parameters['db']))
    $sqliteFile = $parameters['db'];

$db = new PDO('sqlite:' . $sqliteFile);
initDatabase($db);


// 3. Create a Tree
$timethreshold = isset($parameters['timethreshold']) ?
                 $parameters['timethreshold'] :
                 0;
$timeMin = isset($parameters['time_min']) ? $parameters['time_min'] : 0;
$quiet = isset($parameters['quiet']) ? true : false;

$parser = new CachegrindParser2_Input_Parser(
    $parameters['in'], $db, $timethreshold, $timeMin, $quiet);
$parser->createTree();

if (!isset($parameters['quiet']))
    echo ' render output';


// 4. create dot output
$format = new CachegrindParser2_Output_Format(
    $db, $parameters["out"], $parameters['format']);
$format->format();


// 5. done.


/**
 * Initializes the database (drops tables first)
 *
 * @param object $db database handle (PDO)
 */
function initDatabase($db)
{
    $db->exec("DROP TABLE IF EXISTS node;");
    //$db->exec("VACUUM;");

    $db->exec("CREATE TABLE node (
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
}