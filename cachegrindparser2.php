<?php

// TODO add more documentation split up functions into classes

/**
 * This file contains the PhpCachegrindParser2
 *
 * The goal of PhpCachegrindParser2 is to do the parsing more efficiently
 *
 * PHP version 5
 *
 * @author Thomas Bley <thomas.bley@mayflower.de>
 */

error_reporting(E_ALL);

set_include_path(basename(__FILE__) . PATH_SEPARATOR . get_include_path());

define("VERSION", "development");

ini_set( 'memory_limit', '32M' );


// 1. Get the in-/output file and the desired formatting from the command line
$parameters = parseOptions();

// 2. Initialize database
$db = new PDO('sqlite:c:/temp/database.sqlite');
initDatabase($db);

// 3. Create a Tree object
createTree($db, $parameters);

// 4. create dot output
$output = formatDot($db, $parameters);

if (!isset($parameters['quiet']))
	echo ' render output';

// 5. convert dot to image if necesary
if ( $parameters["format"] == "svg" || $parameters["format"] == "png" )
	convertDotToImage($output, $parameters["out"], $parameters["format"]);
else {
	// 6. Write the output file
	file_put_contents($parameters["out"], $output, LOCK_EX);
}

// 7. done.


/**
 * Converts Dot language code into an image
 *
 * @param string $dotCode Dot language code
 * @param string $imageFile File to write image to
 * @param string $format Format: png or svg
 */
function convertDotToImage($dotCode, $imageFile, $format)
{
	$dotFile = tempnam('/tmp', 'cachegrind_' ) . '.dot';
	file_put_contents($dotFile, $dotCode, LOCK_EX);

	// call dot (graphviz package)
	$cmd = "dot -T{$format} -o" . escapeshellarg( $imageFile ) . " " .
		escapeshellarg( $dotFile ) . " 2>&1";

	exec($cmd, $output);
	if ( !empty( $output ) )
		throw new Exception("Failed executing dot:\n" . implode("\n", $output));

	@unlink( $dotFile );
}


/**
 * Initializes the database (drops tables first)
 *
 * @param object $db PDO database handle
 */
function initDatabase($db)
{
	$db->exec("DROP TABLE IF EXISTS node;");
	$db->exec("DROP TABLE IF EXISTS subcall;");
	$db->exec("DROP TABLE IF EXISTS path;");
	//$db->exec("VACUUM;");

	$db->exec("CREATE TABLE node (
		part int,
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


/**
 * returns the summaries from a cachegrind profile
 *
 * @param string $file Filename to parse
 */
function getSummaries($file)
{
	// grep all summaries first
	$rootCosts = array(
		'cost_time' => 0,
		'cost_cycles' => 0,
		'cost_memory' => 0,
		'cost_memory_peak' => 0,
	);

	if (!($fp = fopen($file, 'r')))
		throw new Exception('Unable to read ' . $file);

	$lastData = '';
	while (!feof($fp)) {
		$data = fread($fp, 65536);

		preg_match_all("/summary: (.+?)\n/", $lastData.$data, $summaries);
		if (!empty($summaries[0])) {
			foreach ($summaries[1] as $summary) {
				$summary = explode(' ', $summary);
				$rootCosts['cost_time'] 		+= $summary[0];
				$rootCosts['cost_cycles'] 		+= $summary[2];
				$rootCosts['cost_memory'] 		= max($summary[1],$rootCosts['cost_memory']);
				$rootCosts['cost_memory_peak'] 	= max($summary[3],$rootCosts['cost_memory_peak']);
			}
		}
		$lastData = substr($data, strrpos($data,"\n"));
	}
	fclose($fp);

	return $rootCosts;
}


/**
 * Create a tree from a file in the database
 *
 * @param object PDO database handle
 * @param array $parameters Array containing commandline parameters from getopt
 */
function createTree($db, $parameters)
{
	// TODO split function

	if (!isset($parameters['quiet']))
		echo 'get summaries';

	$rootCosts = getSummaries($parameters['in']);

	if (!($fp = fopen($parameters['in'], 'r')))
		throw new Exception('Unable to read: ' . $parameters['in']);

	$db->beginTransaction();

	$bufferFirstLine = '';
	$bufferLength = 65536;

	$subCallRefs = array();
	$subCallCounts = array();

	$pos = 0;
	$part = 0;

	$dataAmount = filesize($parameters['in']);

	// read file backwards
	while ($pos < $dataAmount) {
		$pos += $bufferLength;

		// don't read before begin of file
		if ($pos > $dataAmount) {
			$bufferLength -= $pos - $dataAmount;
			$pos = $dataAmount;
		}

		// seek from end
		fseek($fp, (-1)*$pos, SEEK_END);
		$data = fread($fp, $bufferLength);

		// split into blocks, append uncomplete block
		$buffer = explode("\n\n", $data . $bufferFirstLine);
		$bufferFirstLine = array_shift($buffer);

		if (!isset($parameters['quiet']))
			echo ' '.round($pos/1048576,2);

		foreach (array_reverse($buffer) as $block) {
			$block = trim($block);
			if ($block == '')
				continue;

			if (strncmp('fl=', $block, 3) != 0 && !is_numeric($block[0])) { // Syntax: fl=<filename> or # # # #

				if (strncmp('====', $block, 4) == 0) // Syntax: ==== NEW PROFILING FILE
					$part++;

				continue;
			}

			$recordNode = array();

			$pattern = '/^fl=(.*?)\nfn=(.*?)\n(\d+) /';
			preg_match($pattern, $block, $func);

			if (empty($func[1])) { // root
				$recordNode['id'] 				= 0;
				$recordNode['function_name'] 	= '{main}';
				$recordNode['filename'] 		= ''; // TODO add filename
				$recordNode['path'] 			= '{main}';
				$recordNode['part'] 			= $part;
				$recordNode['count'] 			= 1;

				// TODO check again
				if (strpos($block, '{main}'))
					continue;

			} else {
				$recordNode['filename'] 		= $func[1];
				$recordNode['function_name'] 	= $func[2];
				$recordNode['id'] 				= $func[3];
				$recordNode['path'] 			= 'undef';
				$recordNode['part'] 			= $part;
				$recordNode['count'] 			= 1;

				$id = $recordNode['id'];
				$funcName = $recordNode['function_name'];

				if (isset($subCallRefs[$id . $funcName]))
					$recordNode['path'] = $subCallRefs[$id . $funcName];
				elseif (isset($subCallRefs[$funcName]))
					$recordNode['path'] = $subCallRefs[$funcName];

				if (isset($subCallCounts[$funcName])) {
					$recordNode['count'] = $subCallCounts[$funcName];
					unset($subCallCounts[$funcName]);
				}
			}

			$pattern = '/^\d+ (\d+) -?(\d+) (\d+) (\d+)$/m';
			preg_match_all($pattern, $block, $costs, PREG_SET_ORDER);

			foreach ($costs as $cost) {
				if (!isset($recordNode['cost_time'])) {
					$recordNode['cost_time'] 		= $cost[1];
					$recordNode['cost_cycles'] 		= $cost[3];
					$recordNode['cost_memory'] 		= $cost[2];
					$recordNode['cost_memory_peak'] = $cost[4];

					$recordNode['cost_time_self'] 			= $cost[1];
					$recordNode['cost_cycles_self'] 		= $cost[3];
					$recordNode['cost_memory_self'] 		= $cost[2];
					$recordNode['cost_memory_peak_self'] 	= $cost[4];
				} else {
					$recordNode['cost_time'] 		+= $cost[1];
					$recordNode['cost_cycles'] 		+= $cost[3];
					$recordNode['cost_memory'] 		= max($cost[2],$recordNode['cost_memory']);
					$recordNode['cost_memory_peak'] = max($cost[4],$recordNode['cost_memory_peak']);
				}
			}

			/* TODO fix
			if (empty($recordNode['cost_time'])) {
				print_r($block);
				exit;
			}
			*/

			if (filter($recordNode['function_name'], $rootCosts, $recordNode, $recordNode['path'], $parameters))
				continue;

			if ($recordNode['function_name']=='{main}')
				$rootArray = $recordNode;

			$fields = implode(',', array_keys($recordNode));
			$values = "'" . implode("','", $recordNode) . "'";
			$db->exec("INSERT INTO node ({$fields}) VALUES({$values})");


			$pattern = "/cfn=(.+?)\n"
					 . "calls=(\d+) \d+ \d+\n"
					 . "(\d+) /";

			preg_match_all($pattern, $block, $subCalls, PREG_SET_ORDER);

			if (!empty($subCalls)) {
				foreach ($subCalls as $subCall) {

					$id = $subCall[3];
					$path = $recordNode['path'] . '##' . $subCall[1];
					$funcName = $subCall[1];
					$count = $subCall[2];

					if (filter($funcName, $rootCosts, array(), $path, $parameters))
						continue;

					$subCallRefs[$id . $funcName] = $path;
					$subCallRefs[$funcName] = $path;

					if (isset($subCallCounts[$funcName]))
						$subCallCounts[$funcName] += $count;
					else
						$subCallCounts[$funcName] = $count;
				}
			}
		}
	}
	$db->commit();
	fclose($fp);
}


/**
 * Format a dot label (including costs, filename, function, etc)
 *
 * @param array $row
 * @param array $rootCosts Total costs of the request Array(cost_cycles=>'', cost_memory=>'', cost_memory_peak=>'', cost_time=>'')
 * @return string Dot language code
 */
function _formatDotLabel($row, $rootCosts)
{
	$nodeName = $row['function_name'];
	$nodeFile = $row['filename'];

	$limit = 40;

	// Format nodeName #{60%}...#{limit - 60% - 3}
	if ( strlen( $nodeName ) > $limit ) {
		$first_length = round($limit * 0.6);
		$second_length = $limit - $first_length - 3;
		$nodeName = substr( $nodeName, 0, $first_length ) . '...' . substr( $nodeName, -$second_length );
	}

	// Format nodeFile ...#{limit - 3}
	if ( strlen( $nodeFile ) > $limit )
		$nodeFile = '...' . substr( $nodeFile, ($limit - 3) * (-1) );

	$output  = "<<table border='0'>\n";
	$output .= "<tr><td border='0' align='center' bgcolor='#ED7404'>";
	$output .= "<font color='white'> " . htmlentities( $nodeFile ) . " <br/>" . htmlentities( $nodeName ) . "</font></td></tr>";

	$output .= '<tr><td><table border="0">';
	$output .= '<tr><td align="right">Incl. Costs</td><td></td>';
	$output .= '<td align="right">Own Costs</td></tr>'."\n";

	foreach ( array('cost_cycles', 'cost_memory', 'cost_memory_peak', 'cost_time') as $key ) {

		$rating = 0;
		$keySelf = $key . '_self';

		$part = $row[$keySelf] / $rootCosts[$key];
		if ($part >= 0.05)
			$rating = 1;
		else
			$rating = 20.0 * ($keySelf / $rootCosts[$key]);

		$bgColor = 'red';
		if ($rating < 0.8)
			$bgColor = 'white';
		elseif ($rating < 0.9)
			$bgColor = 'yellow';

		$output .= "<tr>";
		$output .= "<td align='right' bgcolor='{$bgColor}'>{$row[$key]}</td>\n";
		$output .= "<td align='center' bgcolor='{$bgColor}'> &nbsp;{$key}&nbsp; </td>\n";
		$output .= "<td align='right' bgcolor='{$bgColor}'>{$row[$keySelf]}</td>\n";
		$output .= "</tr>\n";
	}
	$output .= '</table></td></tr>';
	$output .= '</table>>';

	return $output;
}


/**
 * Export a tree from the database to the dot language
 *
 * @param object $db PDO database handle
 * @param array $parameters Commandline parameters from getopt
 * @return string Dot language code
 */
function formatDot($db, $parameters) {

	$output  = "digraph {\nnode [shape=box,style=rounded,fontname=arial,fontsize=17];\nedge [color=lightgrey];\n";

	$sql = "
		SELECT
			sum(cost_time) as cost_time,
			sum(cost_cycles) as cost_cycles,
			max(cost_memory) as cost_memory,
			max(cost_memory_peak) as cost_memory_peak
		FROM node
		WHERE path = '{main}'
		GROUP BY path
	";
	$rootCosts = $db->query($sql)->fetch();

	// TODO add root node with summary?
	// TODO group by filename?
	$sql = "
		SELECT path, sum(count) as count, function_name, filename,
			sum(cost_time) as cost_time,
			sum(cost_cycles) as cost_cycles,
			max(cost_memory) as cost_memory,
			max(cost_memory_peak) as cost_memory_peak,

			sum(cost_time_self) as cost_time_self,
			sum(cost_cycles_self) as cost_cycles_self,
			max(cost_memory_self) as cost_memory_self,
			max(cost_memory_peak_self) as cost_memory_peak_self
		FROM node
		GROUP BY path
		ORDER by path
	";
	$rows = $db->query($sql);
	foreach ($rows as $row) {

		$penWidth = max( 1, ceil(($row['cost_time'] / $rootCosts['cost_time']) * 30)); // thickness of edge

		$edgeLabel =  $row['count'] . 'x';
		$edgeLabel .= ' [' . round($row['cost_time']/1000) . ' ms]';

		$parentPath = substr($row['path'], 0, strrpos($row['path'], '##'));

		$output .= '"' . md5($row['path']) . '" [label=' . _formatDotLabel($row, $rootCosts) . '];'."\n";

		if ($parentPath != '') {
			$output .= '"' . md5($parentPath) . '" -> "' . md5($row['path']) . '"';
			$output .= ' [label="' . $edgeLabel . '",penwidth='.$penWidth.'];'."\n";
		}
	}
	$output .= '}';
	return $output;
}


/**
 * Checks if a node can be filtered
 *
 * @return boolean $functionName true = exclude, false = include
 */
function filter($functionName, $rootCosts, $recordNode, $path, $parameters) {

	$depth = substr_count($path, '##');

	if (strncmp('php::', $functionName, 5) == 0
		&& $functionName != 'php::call_user_func'
		&& $functionName != 'php::preg_replace_callback'
		&& strncmp('php::Reflection', $functionName, 15) != 0)
		return true;

	if ($recordNode && !empty($parameters['timethreshold'])) {

		$minTime = 100; // 100ns
		if ($rootCosts['cost_time'])
			$minTime = $parameters['timethreshold'] * $rootCosts['cost_time'];

		if ($recordNode['cost_time'] < $minTime)
			return true;
	}

	return false;
}


/**
 * Parses the options.
 *
 * Dies after printing some help if the command-line options are wrong.
 *
 * @return array Array with: parameter key => parameter value
 */
function parseOptions()
{
	// Define available Options
	$shortopts  = "";
	$shortopts .= "h";
	$shortopts .= "v";
	$longopts = array(
		"in:",			// required, input file
		"out:",			// required, output file
		"format:",  	// required, output format
		"parts::",  	// optional, extract only some parts
		"exclude::",	// optional, skip parts by matching one of the excludes
		"include::",	// optional, include only parts by matching one of the includes
		"depth::",		// optional, max. tree depth
		"timethreshold::", // filter nodes by time
		"quiet",		// optional, don't output additional information
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
	if ((empty($opts["in"])	 || is_array($opts["in"])) ||
		(empty($opts["out"])	|| is_array($opts["out"])) ||
		(empty($opts["format"]) || is_array($opts["format"]))) {
		usage();
		exit(1);
	}

	if (!in_array($opts["format"], array("xml","dot","svg","png"))) {
		usageFormatters();
		exit(2);
	}

	if (!file_exists($opts["in"])) {
		inputError();
		exit(3);
	}

	return $opts;
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
	echo "Usage: php cachegrindparser2.php --in <file_in> --out <file_out> --depth=# --timethreshold=0.## --filter ... --format xml|dot|svg|png --parts=#,# --exclude=<strings> --include=<strings> --quiet\n\n";
	echo "Optional: --filter, --parts, --exclude, --include, --quiet\n";
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
