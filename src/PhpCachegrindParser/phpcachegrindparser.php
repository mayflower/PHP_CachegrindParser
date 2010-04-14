<?php

/**
 * This file contains PhpCachegrindParser's entry point.
 *
 * PHP version 5
 *
 * @author Kevin-Simon Kohlmeyer <simon.kohlmeyer@googlemail.com>
 */

namespace PhpCachegrindParser;

set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__);

require_once "Output/XMLFormatter.php";
require_once "Output/DotFormatter.php";
require_once "Input/Parser.php";

define("VERSION", "development");

// We need to do the following:
// 1. Get the in-/output file and the desired formatting from the command line
$parameters = parseOptions();

// 2. Create a Parser object
$parser = new Input\Parser($parameters["input"]);

// 3. Format it according to (1)
$output = $parameters["formatter"]->format($parser);

// 4. Print it to the file given in (1)
//NOTE: Locking might not be neccessary here
file_put_contents($parameters["output"], $output, LOCK_EX);

// We're done.

/**
 * Parses the options.
 *
 * Dies after printing some help if the command-line options are wrong.
 *
 * @return array Array with: input => input data
 *                           output => output filename
 *                           formatter => output formatter
 */
function parseOptions()
{
    // Define available Options
    $shortopts  = "";
    $shortopts .= "h";
    $shortopts .= "v";
    $longopts = array(
        "in:",
        "out:",
        "format:",
        "help",
        "version"
    );
    // get them
    $opts = getopt($shortopts, $longopts);

    // Check if the user just wants info.
    if (isset($opts["help"]) || isset($opts["h"])) {
        usage();
        exit;
    } else if (isset($opts["version"]) || isset($opts["v"])) {
        version();
        exit;
    }

    // Check if we're given each mandatory argument exactly once
    if ((!isset($opts["in"])     || is_array($opts["in"])) ||
        (!isset($opts["out"])    || is_array($opts["out"])) ||
        (!isset($opts["format"]) || is_array($opts["format"]))) {
        usage();
        exit(1);
    }

    // Select the Formatter
    switch ($opts["format"]) {
    case 'xml':
        $ret["formatter"] = new Output\XMLFormatter();
        break;
    case 'dot':
        $ret["formatter"] = new Output\DotFormatter();
        break;
    default:
        usageFormatters();
        exit(1);
    }

    $ret["input"] = file_get_contents($opts["in"]);
    if (!$ret["input"]) {
        inputError();
    }
    $ret["output"] = $opts["out"];

    return $ret;
}

/**
 * Prints the version of this script to stdout.
 */
function version()
{
    echo "PhpCachegrindParser version " . VERSION . "\n";
}

/**
 * Prints information about the Formatters.
 */
function usageFormatters()
{
    //TODO: write the usage output.
    echo "Write me.\n";
}

/**
 * Prints an explanation about a missing or empty input file.
 */
function inputError()
{
    echo <<<EOT
Couldn't find valid input data. Check that the input file exists
and contains usable data.
EOT;
}

/**
 * Prints usage information to standard output.
 */
function usage()
{
    //TODO: Write the usage output.
    echo "Write me.\n";
}

