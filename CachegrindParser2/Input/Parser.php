<?php
/**
 * This file contains the class CachegrindParser\Input\Parser.
 *
 * PHP version 5
 *
 * @author Thomas Bley <thomas.bley@mayflower.de>
 */

/**
 * This class creates a tree in the database from a cachegrind file
 *
 * For an easy example of a cachegrind profile output and the corresponding
 * php file, see ./Examples/example.*
 */
class CachegrindParser2_Input_Parser
{
	/**
	 * input file
	 * @var string
	 */
	private $_file = '';


	/**
	 * dont output debug/progress information
	 * @var boolean
	 */
	private $_quiet = false;


	/**
	 * Percentage of total cost_time to drop a node
	 * @var float
	 */
	private $_timethreshold = 0;


	/**
	 * database handle (PDO)
	 * @var object
	 */
	private $_db = null;


	/**
	 * Constructor for CachegrindParser2_Input_Parser
	 *
	 * @param string $file Filename to parse
	 * @param object $db Database handle (PDO)
	 * @param float $timethreshold Percentage of total cost_time to drop a node
	 * @param boolean $quiet dont output debug/progress information
	 */
	public function __construct($file, $db, $timethreshold = 0, $quiet = false)
	{
		if (empty($file) || !file_exists($file) || filesize($file) == 0 || !is_readable($file))
			throw new Exception('Cannot read ' . $file);

		$this->_file 			= $file;
		$this->_db 				= $db;
		$this->_quiet 			= $quiet;
		$this->_timethreshold 	= $timethreshold;
	}


	/**
	 * Create a tree in the database from a cachegrind file
	 */
	public function createTree()
	{
		$this->_db->beginTransaction();

		if (!$this->_quiet)
			echo 'get summaries';

		// get "summary: *" aggregated
		$rootCosts = $this->_getSummaries();

		if (!($fp = fopen($this->_file, 'r')))
			throw new Exception('Unable to read: ' . $this->_file);

		$bufferFirstLine = '';
		$bufferLength = 65536;

		$subCallRefs = array();
		$subCallCounts = array();

		$pos = 0;
		$part = 0;
		$request = '';

		$dataAmount = filesize($this->_file);

		// read file backwards (!)
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
			if ($pos < $dataAmount)
				$bufferFirstLine = array_shift($buffer);

			if (!$this->_quiet)
				echo ' '.round($pos/1048576,2);

			foreach (array_reverse($buffer) as $block) {
				$block = trim($block);
				if ($block == '')
					continue;

				// Syntax: fl=<filename> or # # # # or ==== NEW PROFILING FILE
				if (strncmp('fl=', $block, 3) != 0 && !is_numeric($block[0])) {

					if (strncmp('====', $block, 4) == 0) {
						$this->_db->exec("UPDATE node SET request='{$request}' WHERE part='{$part}' AND request IS NULL");
						$part++;
					}
					continue;
				}

				$recordNode = array();

				// root node: has no id or subcount-data in current block
				$pattern = '/^fl=(.*?)\nfn=(.*?)\n(\d+) |^fl=(.*?)\n/';
				preg_match($pattern, $block, $func);

				if (empty($func[2])) { // root
					$recordNode['id'] 				= 0;
					$recordNode['function_name'] 	= '{main}';
					$recordNode['filename'] 		= '';
					$recordNode['path'] 			= '{main}';
					$recordNode['part'] 			= $part;
					$recordNode['count'] 			= 1;

					if (!empty($func[4]) && strpos($block,'{main}')) { // set the request filename
						$request = basename($func[4]);
						continue;
					}
				} else {
					$recordNode['filename'] 		= $func[1];
					$recordNode['function_name'] 	= $func[2];
					$recordNode['id'] 				= $func[3];
					$recordNode['path'] 			= 'undef';
					$recordNode['part'] 			= $part;
					$recordNode['count'] 			= 1;

					$id = $recordNode['id'];
					$funcName = $recordNode['function_name'];

					// parent node from stack by id+name or by name (ID can be 0 in some cases!)
					if (isset($subCallRefs[$id . $funcName]))
						$recordNode['path'] = $subCallRefs[$id . $funcName];

					elseif (isset($subCallRefs[$funcName]))
						$recordNode['path'] = $subCallRefs[$funcName];

					// add call count
					if (isset($subCallCounts[$funcName])) {
						$recordNode['count'] = $subCallCounts[$funcName];
						unset($subCallCounts[$funcName]);
					}
				}

				// sum up costs: self + sub-calls
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

				// Workaround: corrupt file format
				if (empty($recordNode['cost_time']))
					$recordNode['cost_time'] = 0;

				// Drop out unneeded nodes
				if ($this->_filter($recordNode['function_name'], $rootCosts, $recordNode, $recordNode['path']))
					continue;

				if ($recordNode['function_name']=='{main}')
					$rootArray = $recordNode;

				$fields = implode(',', array_keys($recordNode));
				$values = "'" . implode("','", $recordNode) . "'";
				$this->_db->exec("INSERT INTO node ({$fields}) VALUES({$values})");


				// process subcalls
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

						if ($this->_filter($funcName, $rootCosts, array(), $path))
							continue;

						// add subcalls to stack by id+name or name (ID can be 0 in some cases!)
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
		fclose($fp);
		$this->_db->commit();
	}


	/**
	 * Checks if a node can be filtered (removed from the tree)
	 *
	 * @param string $functionName Function name
	 * @param array $rootCosts Array(Keys: cost_time)
	 * @param array $recordNode Array(Keys: cost_time)
	 * @param string $path Node path
	 * @return boolean $functionName true = exclude, false = include
	 */
	private function _filter($functionName, $rootCosts, $recordNode, $path) {

		// $depth = substr_count($path, '##');

		if (strncmp('php::', $functionName, 5) == 0
			&& $functionName != 'php::call_user_func'
			&& $functionName != 'php::preg_replace_callback'
			&& strncmp('php::Reflection', $functionName, 15) != 0)
			return true;

		if ($recordNode && $this->_timethreshold!=0) {

			$minTime = 100; // 100ns
			if ($rootCosts['cost_time'])
				$minTime = $this->_timethreshold * $rootCosts['cost_time'];

			if ($recordNode['cost_time'] < $minTime)
				return true;
		}

		return false;
	}


	/**
	 * returns the summaries from a cachegrind profile
	 */
	private function _getSummaries()
	{
		$rootCosts = array(
			'cost_time' => 0,
			'cost_cycles' => 0,
			'cost_memory' => 0,
			'cost_memory_peak' => 0,
		);

		if (!($fp = fopen($this->_file, 'r')))
			throw new Exception('Unable to read ' . $this->_file);

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
}
