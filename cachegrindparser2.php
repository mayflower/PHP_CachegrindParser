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

//ini_set('memory_limit', '32M');

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

$dbo = new PDO('sqlite:' . $sqliteFile);
CachegrindParser2_Input_Parameters::initDatabase($dbo);


// 3. Create a Tree
$timethreshold = isset($parameters['timethreshold']) ?
                 $parameters['timethreshold'] :
                 0;
$timeMin = isset($parameters['time_min']) ? $parameters['time_min'] : 0;
$quiet = isset($parameters['quiet']) ? true : false;

$parser = new CachegrindParser2_Input_Parser(
    $parameters['in'], $dbo, $timethreshold, $timeMin, $quiet);
$parser->createTree();

if (!isset($parameters['quiet']))
    echo ' render output';


// 4. create dot output
$format = new CachegrindParser2_Output_Format(
    $dbo, $parameters["out"], $parameters['format']);
$format->format();


// 5. done.