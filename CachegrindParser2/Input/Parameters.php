<?php
/**
 * This file contains the class CachegrindParser\Input\Parameters.
 *
 * PHP version 5
 *
 * @author Thomas Bley <thomas.bley@mayflower.de>
 * @version 2.0
 */

/**
 * This class reads and validates parameters passed from the commandline
 */
class CachegrindParser2_Input_Parameters
{

	/**
	 * Commandline parameters
	 * @var Array
	 */
	private $_parameters = array();


	/**
	 * Parses the options.
	 *
	 * Dies after printing some help if the command-line options are wrong.
	 *
	 * @return array Array with: parameter key => parameter value
	 */
	public function __construct()
	{
		// Define available Options
		$shortopts  = '';
		$shortopts .= 'h';
		$shortopts .= 'v';
		$longopts = array(
			'in:',			// required, input file
			'out:',			// required, output file
			'format:',  	// required, output format
			'parts::',  	// optional, extract only some parts
			'exclude::',	// optional, skip parts by matching one of the excludes
			'include::',	// optional, include only parts by matching one of the includes
			'depth::',		// optional, max. tree depth
			'timethreshold::', // filter nodes by time
			'quiet',		// optional, don't output additional information
			'help',
			'version'
		);

		// get them
		$opts = getopt($shortopts, $longopts);

		// Check if the user just wants info.
		if (isset($opts['help']) || isset($opts['h'])) {
			$this->_usage();
			exit;
		}

		if (isset($opts['version']) || isset($opts['v'])) {
			$this->_version();
			exit;
		}

		// Check if we're given each mandatory argument exactly once
		if ((empty($opts['in'])	 || is_array($opts['in'])) ||
			(empty($opts['out'])	|| is_array($opts['out'])) ||
			(empty($opts['format']) || is_array($opts['format']))) {
			echo "Error: missing parameters\n";
			$this->_usage();
			exit(1);
		}

		if (!in_array($opts['format'], array('xml','dot','svg','png'))) {
			$this->_usageFormatters();
			exit(2);
		}

		if (!file_exists($opts['in'])) {
			$this->_inputError();
			exit(3);
		}
		$this->_parameters = $opts;
	}


	/**
	 * return the commandline parameters
	 */
	public function getParameters()
	{
		return $this->_parameters;
	}


	/**
	 * Prints the version of this script to stdout.
	 */
	private function _version()
	{
		echo "PhpCachegrindParser version " . VERSION . "\n";
	}


	/**
	 * Prints information about the Formatters.
	 */
	private function _usageFormatters()
	{
		echo "Error: invalid formatter\n";
		$this->_usage();
	}


	/**
	 * Prints an explanation about a missing or empty input file.
	 */
	private function _inputError()
	{
		echo "Couldn't find valid input data. Check that the input file exists and contains usable data.\n";
	}


	/**
	 * Prints usage information to standard output.
	 */
	private function _usage()
	{
		echo "Usage: php cachegrindparser2.php --in <file_in> --out <file_out> --format xml|dot|svg|png\n\n";
		echo "Optional: --timethreshold=0.## --quiet\n";
		echo "Dot to SVG with letter page size: dot -Gsize=11,7 -Gratio=compress -Gcenter=true -Tsvg -o<file_out> <file_in>\n";
		echo "Dot to SVG with screen size: dot -Tsvg -o<file_out> <file_in>\n";
		echo "Note: SVG export needs the package 'graphviz'\n";
	}
}
