<?php

/**
 * This file contains PhpCachegrindParser's entry point.
 *
 * PHP version 5
 *
 * @author Kevin-Simon Kohlmeyer <simon.kohlmeyer@googlemail.com>
 */

set_include_path(__DIR__ . PATH_SEPARATOR . get_include_path());

require_once "CachegrindParser/Output/XMLFormatter.php";
require_once "CachegrindParser/Output/DotFormatter.php";
require_once "CachegrindParser/Input/Parser.php";
require_once "CachegrindParser/Input/NoPhpFilter.php";
require_once "CachegrindParser/Input/IncludeFilter.php";

define("VERSION", "development");

// We need to do the following:
// 1. Get the in-/output file and the desired formatting from the command line
$parameters = parseOptions();

// 2. Create a Parser object
$parser = new CachegrindParser\Input\Parser($parameters["input"]);

// 3. Add the filters
foreach ($parameters['filters'] as $filter) {
    $parser->addFilter($filter);
}

// 4. Format it according to (1)
$output = $parameters["formatter"]->format($parser);

// 5. Print it to the file given in (1)
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
        "filter:",
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
        $ret["formatter"] = new CachegrindParser\Output\XMLFormatter();
        break;
    case 'dot':
        $ret["formatter"] = new CachegrindParser\Output\DotFormatter();
        break;
    default:
        usageFormatters();
        exit(2);
    }

    // Check for filters
    $ret['filters'] = array();
    if (isset($opts['filter'])) {
        if (!(gettype($opts['filter']) === 'array')) {
            $opts['filter'] = array($opts['filter']);
        }
        foreach ($opts['filter'] as $name) {
            switch ($name) {
            case 'nophp':
                $ret['filters'][] = new CachegrindParser\Input\NoPhpFilter();
                break;
            case 'include':
                $ret['filters'][] = new CachegrindParser\Input\IncludeFilter();
                break;
            default:
                usageFilters();
                exit(3);
            }
        }
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

/**
 * Prints information about Filters.
 */
function usageFilters()
{
    //TODO: write the filters usage output.
    echo "Write me.\n";
}
