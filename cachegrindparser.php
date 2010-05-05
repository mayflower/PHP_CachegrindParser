<?php

/**
 * This file contains PhpCachegrindParser's entry point.
 *
 * PHP version 5
 *
 * @author Kevin-Simon Kohlmeyer <simon.kohlmeyer@googlemail.com>
 * @author Thomas Bley <thomas.bley@mayflower.de>
 */

set_include_path(__DIR__ . PATH_SEPARATOR . get_include_path());

require_once "CachegrindParser/Output/XMLFormatter.php";
require_once "CachegrindParser/Output/DotFormatter.php";
require_once "CachegrindParser/Input/Parser.php";
require_once "CachegrindParser/Input/NoPhpFilter.php";
require_once "CachegrindParser/Input/IncludeFilter.php";
require_once "CachegrindParser/Input/DepthFilter.php";
require_once "CachegrindParser/Input/TimeThresholdFilter.php";
use CachegrindParser\Input;

define("VERSION", "development");

ini_set( 'memory_limit', '1024M' );

// We need to do the following:
// 1. Get the in-/output file and the desired formatting from the command line
$parameters = parseOptions();

// 2. Create a Tree object
$tree = createTree( $parameters["input"], $parameters["quiet"] );

if ( !$parameters["quiet"] )
	echo " apply filters";

// 3. Add the filters
foreach ($parameters['filters'] as $filter) {
    $tree->addFilter($filter);
}
$tree->filterTree();

// 4. Format it according to (1)
$output = $parameters["formatter"]->format($tree);

// convert dot to svg or png
if ( $parameters["format"] == "svg" || $parameters["format"] == "png" ) {
	$dotFile = tempnam( "/tmp", "cachegrind_" ) . ".dot";
	file_put_contents( $dotFile, $output, LOCK_EX);

	// call dot (graphviz package)
	$cmd = "dot -T{$parameters["format"]} -o" . escapeshellarg( $parameters["output"] ) . " " . 
							escapeshellarg( $dotFile ) . " 2>&1";
	
	exec( $cmd, $output );
	if ( !empty( $output ) )
		throw new Exception( "Failed executing dot:\n" .
							 implode( "\n", $output ) );

	@unlink( $dotFile );
}
else {
	// 5. Print it to the file given in (1)
	//NOTE: Locking might not be neccessary here
	file_put_contents($parameters["output"], $output, LOCK_EX);
}

// We're done.


/**
 * Create a tree from a file
 * 
 * @param string $file Filename to parse (can be multi part)
 * @param boolean $quiet true: Don't print out progress information
 */
function createTree( $file, $quiet )
{
	// maximum limit to parse a part
	$limit = 10*1024*1024;
	
	$numParts = 0;
	$numLines = 0;
	$numData = 0;
	$inputData = '';

	// create empty tree
	$tree = Input\Parser::getRootTree();
	
	if (!($fp = fopen( $file, 'r' )))
		throw new Exception( 'Unable to read ' . $file );

	$fpFilesize = filesize( $file );

	while ( !feof( $fp ) ) {
	
		$line = fgets($fp);
		$numLines++;
		$numData += strlen( $numData );
		$progress = number_format( ( ( $numData / $fpFilesize ) * 100), 2) . '%';

		// check for a new part (boundary match or end of file)
		if ( strpos($line, '==== NEW PROFILING FILE') === 0 || feof( $fp ) ) {
			if ( trim($inputData) != '' && strlen($inputData) < $limit ) {
	
				if ( !$quiet )
					echo "## part {$numParts} length ".strlen($inputData)." line {$numLines} progress {$progress}";
				
				$parser = new Input\Parser( $inputData );
				$currTree = $parser->getCallTree();
				
				if ( !$quiet )
					echo " combine similar";
				$currTree->combineSimilarSubtrees();
				
				if ( !$quiet )
					echo " combine trees";
				$tree->combineTrees( $currTree );

				if ( !$quiet ) {
					echo " memory ".memory_get_usage(true);
					echo " memory peak ".memory_get_peak_usage(true);
					echo "\n";
				}
			}
			if ( strlen($inputData) > $limit && !$quiet )
				echo " skip part {$numParts}, too large: length ".strlen($inputData)." line {$numLines} progress {$progress}\n";
				
			$inputData = '';
			$numParts++;

		} else {
			$inputData .= $line;
		}
	}
	fclose($fp);
	
	return $tree;
}

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
        "in:",		// required
        "out:",		// required
        "filter::", // optional
    	"quiet::",  // optional
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
    case 'svg':
    case 'png':
    	$ret["formatter"] = new CachegrindParser\Output\DotFormatter();
        break;
    default:
        usageFormatters();
        exit(2);
    }
    $ret["format"] = $opts["format"];
    
    // Check for filters
    $ret['filters'] = array();
    if (isset($opts['filter'])) {
        if (!(gettype($opts['filter']) === 'array')) {
            $opts['filter'] = array($opts['filter']);
        }
        foreach ($opts['filter'] as $name) {
            switch ($name) {
            case 'nophp':
                $ret['filters'][] = new Input\NoPhpFilter();
                break;
            case 'include':
                $ret['filters'][] = new Input\IncludeFilter();
                break;
            case (strncmp($name, 'depth=', 6) == 0):
                $depth = (integer) substr($name, 6);
                if ($depth <= 0) {
                    usageFilters();
                    exit(3);
                }
                $ret['filters'][] = new Input\DepthFilter($depth);
                break;
            case (strncmp($name, 'timethreshold=', 14) == 0):
               $percentage = (float) substr($name, 14);
               if ($percentage < 0 || $percentage > 1) {
                   usageFilters();
                   exit(3);
               }
               $ret['filters'][] = new Input\TimeThresholdFilter($percentage);
               break;
            default:
            	echo "Invalid filter: {$name}\n";
                usageFilters();
                exit(3);
            }
        }
    }
    
    $ret["input"] = $opts["in"];
    if (!file_exists($ret["input"])) {
        inputError();
        exit(3);
    }
    $ret["output"] = $opts["out"];

    $ret["quiet"] = isset( $opts["quiet"] ) ? true : false;
    
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
	echo "Error: invalid formatter\n";
	usage();
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
	echo "Error: missing parameters\n";
	echo "Usage: php cachegrindparser.php --in <file_in> --out <file_out> --filter=nophp|include|depth=#|timethreshold=0.## --filter ... --format xml|dot|svg|png --quiet\n\n";
	echo "Optional: --filter, --quiet\n";
	echo "Dot to SVG with letter page size: dot -Gsize=11,7 -Gratio=compress -Gcenter=true -Tsvg -o<file_out> <file_in>\n";
	echo "Dot to SVG with screen size: dot -Tsvg -o<file_out> <file_in>\n";
	echo "Note: SVG export needs the package 'graphviz'\n";
}

/**
 * Prints information about Filters.
 */
function usageFilters()
{
	echo "Error: invalid filters\n";
	usage();
}
