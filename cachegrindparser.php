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

ini_set('memory_limit', '1524M');

// We need to do the following:
// 1. Get the in-/output file and the desired formatting from the command line
$parameters = cachegrindparser::parseOptions();

// 2. Create a Tree object
$tree = cachegrindparser::createTree(
    $parameters["input"], $parameters["quiet"], $parameters['parts'],
    $parameters['exclude'], $parameters['include']
);

// 3. Filter the tree
if (!$parameters["quiet"])
    echo " apply filters";

foreach ($parameters['filters'] as $filter) {
    $tree->addFilter($filter);
}
$tree->filterTree();

// 4. Format it according to (1)
if (!$parameters["quiet"])
    echo " render output";

$output = $parameters["formatter"]->format($tree);

// convert dot to svg or png
if ($parameters["format"] == "svg" || $parameters["format"] == "png") {
    $dotFile = tempnam("/tmp", "cachegrind_") . ".dot";
    file_put_contents($dotFile, $output, LOCK_EX);

    // call dot (graphviz package)
    $cmd = "dot -T{$parameters["format"]} -o" .
            escapeshellarg($parameters["output"]) . " " .
            escapeshellarg($dotFile) . " 2>&1";

    exec($cmd, $output);
    if (!empty($output))
        throw new Exception("Failed executing dot:\n" .
                             implode("\n", $output));

    @unlink($dotFile);
} else {
    // 5. Print it to the file given in (1)
    //NOTE: Locking might not be neccessary here
    file_put_contents($parameters["output"], $output, LOCK_EX);
}

// We're done.


class cachegrindparser
{

    /**
     * Create a tree from a file
     *
     * @param string $file Filename to parse (can be multi part)
     * @param boolean $quiet true: Don't print out progress information
     * @param array $parts Array(int min, int max)
     * @param array $excludes skip parts by matching one of the excludes
     * @param array $includes use only parts by matching one of the includes
     */
    public static function createTree($file, $quiet, $parts, $excludes,
        $includes)
    {
        // maximum limit to parse a part, 64M
        $limit = 64*1048576;

        $numParts = 0;
        $numLines = 0;
        $numData = 0;
        $inputData = '';

        // create empty tree
        $tree = Input\Parser::getRootTree();

        if (!($fpr = fopen($file, 'r')))
            throw new Exception('Unable to read ' . $file);

        $fpFilesize = filesize($file);

        while (!feof($fpr)) {

            $line = fgets($fpr);
            $numLines++;
            $numData += strlen($line);
            $progress = number_format((($numData / $fpFilesize) * 100), 2) .
                '%';

            // check for a new part (boundary match or end of file)
            if (strpos($line, '==== NEW PROFILING FILE') === 0 || feof($fpr)) {

                // empty part
                if (strlen($inputData) < 100) {
                    $inputData = '';
                    continue;
                }
                $numParts++;

                // min. / max. parts
                if (!empty($parts[0]) && $parts[0] > $numParts) {
                    if (!$quiet)
                        echo "-- skip part {$numParts}, parts range, read".
                             " progress {$progress}\n";

                    $inputData = '';
                    continue;
                }
                if (!empty($parts[1]) && $parts[1] < $numParts)
                    break;

                if (strlen($inputData) > $limit) {
                    if (!$quiet)
                        echo "-- skip part {$numParts}, too large: length ".
                            strlen($inputData)." line {$numLines} read ".
                            "progress {$progress}\n";

                    $inputData = '';
                    continue;
                }

                foreach ($excludes as $key => $exclude) {
                    $excludes[$key] = preg_quote($exclude);
                }
                $regexp = implode('|', $excludes);
                if (!empty($regexp) && preg_match("!{$regexp}!", $inputData)) {
                    if (!$quiet)
                        echo "-- skip part {$numParts}, match exclusion, read ".
                             "progress {$progress}\n";

                    $inputData = '';
                    continue;
                }

                foreach ($includes as $key => $include) {
                    $includes[$key] = preg_quote($include);
                }
                $regexp = implode('|', $includes);
                if (!empty($regexp) && preg_match("!{$regexp}!", $inputData)) {
                    if (!$quiet)
                        echo "-- skip part {$numParts}, match exclusion, read ".
                             "progress {$progress}\n";

                    $inputData = '';
                    continue;
                }


                if (trim($inputData) != '') {

                    if (!$quiet)
                        echo "## part {$numParts} length ".strlen($inputData).
                             " line {$numLines} read progress {$progress}";

                    $parser = new Input\Parser($inputData);
                    $currTree = $parser->getCallTree();

                    if (strlen($inputData) < 10 * 1048576) {
                        if (!$quiet)
                            echo " combine similar";

                        $currTree->combineSimilarSubtrees();
                    }

                    if (!$quiet)
                        echo " combine trees";

                    $tree->combineTrees($currTree);

                    if (!$quiet) {
                        echo " memory ".memory_get_usage(true);
                        echo " memory peak ".memory_get_peak_usage(true);
                        echo "\n";
                    }
                }
                $inputData = '';

            } else {
                $inputData .= $line;
            }
        }
        fclose($fpr);

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
    public static function parseOptions()
    {
        // Define available Options
        $shortopts  = "";
        $shortopts .= "h";
        $shortopts .= "v";
        $longopts = array(
            "in:",            // required, input file
            "out:",            // required, output file
            "format:",      // required, output format
            "filter::",     // optional, input tree filter
            "parts::",      // optional, extract only some parts
            "exclude::",    // optional, skip parts by matching one of the
                            // excludes
            "include::",    // optional, include only parts by matching one
                            // of them
            "quiet",        // optional, don't output additional information
            "help",
            "version"
        );
        // get them
        $opts = getopt($shortopts, $longopts);

        // Check if the user just wants info.
        if (isset($opts["help"]) || isset($opts["h"])) {
            cachegrindparser::usage();
            exit;
        } else if (isset($opts["version"]) || isset($opts["v"])) {
            cachegrindparser::version();
            exit;
        }

        // Check if we're given each mandatory argument exactly once
        if ((!isset($opts["in"])     || is_array($opts["in"])) ||
            (!isset($opts["out"])    || is_array($opts["out"])) ||
            (!isset($opts["format"]) || is_array($opts["format"]))) {
            cachegrindparser::usage();
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
            cachegrindparser::usageFormatters();
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
                        cachegrindparser::usageFilters();
                        exit(3);
                    }
                    $ret['filters'][] = new Input\DepthFilter($depth);
                    break;
                case (strncmp($name, 'timethreshold=', 14) == 0):
                    $percentage = (float) substr($name, 14);
                    if ($percentage < 0 || $percentage > 1) {
                        cachegrindparser::usageFilters();
                        exit(3);
                    }
                    $ret['filters'][] = new Input\TimeThresholdFilter(
                        $percentage
                    );
                    break;
                default:
                    echo "Invalid filter: {$name}\n";
                    cachegrindparser::usageFilters();
                    exit(3);
                }
            }
        }

        $ret["input"] = $opts["in"];
        if (!file_exists($ret["input"])) {
            cachegrindparser::inputError();
            exit(3);
        }
        $ret["output"] = $opts["out"];

        // only extract some parts of the file
        $ret["parts"] = isset($opts["parts"]) ?
                        explode(',', $opts["parts"]) :
                        array();

        // skip parts by matching one of the excludes
        $ret["exclude"] = isset($opts["exclude"]) ?
                          explode(',', $opts["exclude"]) :
                          array();

        // include only parts by matching one of the includes
        $ret["include"] = isset($opts["include"]) ?
                          explode(',', $opts["include"]) :
                          array();

        // don't display additional information
        $ret["quiet"] = isset($opts["quiet"]) ? true : false;

        return $ret;
    }

    /**
     * Prints the version of this script to stdout.
     */
    public static function version()
    {
        echo "PhpCachegrindParser version " . VERSION . "\n";
    }

    /**
     * Prints information about the Formatters.
     */
    public static function usageFormatters()
    {
        echo "Error: invalid formatter\n";
        cachegrindparser::usage();
    }

    /**
     * Prints an explanation about a missing or empty input file.
     */
    public static function inputError()
    {
        echo "
            Couldn't find valid input data. Check that the input file exists
            and contains usable data.
        ";
    }

    /**
     * Prints usage information to standard output.
     */
    public static function usage()
    {
        echo "Error: missing parameters\n";
        echo "Usage: php cachegrindparser.php --in <file_in> --out <file_out> ".
             "--filter=nophp|include|depth=#|timethreshold=0.## --filter ... ".
             "--format xml|dot|svg|png --parts=#,# --exclude=<strings> ".
             "--include=<strings> --quiet\n\n";
        echo "Optional: --filter, --parts, --exclude, --include, --quiet\n";
        echo "Dot to SVG with letter page size: dot -Gsize=11,7 ".
             "-Gratio=compress -Gcenter=true -Tsvg -o<file_out> <file_in>\n";
        echo "Dot to SVG with screen size: dot -Tsvg -o<file_out> <file_in>\n";
        echo "Note: SVG export needs the package 'graphviz'\n";
    }

    /**
     * Prints information about Filters.
     */
    public static function usageFilters()
    {
        echo "Error: invalid filters\n";
        cachegrindparser::usage();
    }
}