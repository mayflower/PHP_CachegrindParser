<?php

/**
 * This file contains the class CachegrindParser\Input\Parser.
 *
 * PHP version 5
 *
 * @author Kevin-Simon Kohlmeyer <simon.kohlmeyer@googlemail.com>
 * @author Thomas Bley <thomas.bley@mayflower.de>
 */

namespace CachegrindParser\Input;

use CachegrindParser\Data;

require_once "CachegrindParser/Data/RawEntry.php";
require_once "CachegrindParser/Data/RawCall.php";
require_once "CachegrindParser/Data/CallTree.php";
require_once "CachegrindParser/Data/CallTreeNode.php";

/**
 * This class converts input to an object representation.
 *
 * For each input, a instance of parser has to be created.
 * It then parses the input when an object representation is
 * requested.
 *
 * At the moment, it does not do any caching.
 */
class Parser
{
    /** Stores the input for later use. */
    private $inputData;

    /**
     * Creates a new Parser instance.
     *
     * @param string $inputData The input to work on.
     */
    function __construct($inputData)
    {
        $this->inputData = $inputData;
    }

    /**
     * Create a root node
     */
    public static function getRoot()
    {
        $rootCosts = array(
            'time' => 0,
            'mem'  => 0,
            'cycles' => 0,
            'peakmem' => 0,
        );
        $rootEntry = new Data\RawEntry("", "{root}", $rootCosts);
        return Data\CallTreeNode::fromRawEntry($rootEntry);
    }

    /**
     * Create a root tree
     */
    public static function getRootTree()
    {
        $summary = array(
            'time' => 0,
            'mem'  => 0,
            'cycles' => 0,
            'peakmem' => 0,
        );
        return new Data\CallTree(self::getRoot(), $summary);
    }

    /**
     * Returns the call tree. Subtrees are not automatically combined,
     * it can be done by calling combineSimilarSubtrees() on the returned tree.
     *
     * @return CachegrindParser\Data\CallTree The calltree.
     */
    public function getCallTree()
    {
        $entries = array_reverse($this->getEntryList());

        $parent = array();
        $childrenLeft = array();
        $root = self::getRoot();
        array_push($parent, $root);

        foreach($entries as $key=>$entry) {
            $node = Data\CallTreeNode::fromRawEntry($entry);
            end($parent)->addChild($node);
            array_push($childrenLeft, array_pop($childrenLeft) -1);

            if(end($childrenLeft) == 0) {
                array_pop($parent);
                array_pop($childrenLeft);
            }
            $subcalls = $entry->getSubcalls();
            if ($subcalls != 0) {
                array_push($parent, $node);
                array_push($childrenLeft, $subcalls);
            }
            unset($entries[$key]);
        }

        // Find the summary, default is non empty summary to avoid
        // division by zero errors in filters
        $summary = array(
        	'time' => 1,
        	'mem' => 1,
        	'cycles' => 1,
        	'peakmem' => 1
        );
        foreach (explode("\n", $this->inputData) as $line) {
            if (strncmp($line, 'summary:', 8) == 0) {
                $summaryTokens = explode(' ', $line);
                $summary['time']    = $summaryTokens[1];
                $summary['mem']     = $summaryTokens[2];
                $summary['cycles']  = $summaryTokens[3];
                $summary['peakmem'] = $summaryTokens[4];
                break;
            }
        }

        return new Data\CallTree($root, $summary);
    }


    /*
     * Generates an array of entries.
     *
     * Each function block in the input file is represented by an object
     * in this array.
     *
     * @return array Array of CachegrindParser\Entry objects.
     */
    private function getEntryList()
    {
        $lines = explode("\n", trim($this->inputData));

    	// This makes our array indices the same as the file's line numbers
        array_unshift($lines, '');
        $curLine = 7; // The first 6 lines are metadata
        $entries = array(); // Here we'll store the generated entries.

        //TODO: More input validation here
        while($curLine + 1 < count($lines)) {
            if (strncmp($lines[$curLine], 'fl=', 3) != 0) {
                // Don't know what to do, panic
                die("parse error on line {$curLine}. (Script line: "
                    . __LINE__ . ", line: {$lines[$curLine]})\n");
            } else {
                // Regular block
                // Strip fl= from file name and fn from funcname.
                $fl = substr($lines[$curLine], 3);
                $fn = substr($lines[$curLine + 1], 3);
                if (strncmp($fn, '{main}', 6) == 0) {
                    $costs = self::parseCostLine($lines[$curLine + 5]);
                    $curLine += 6;
                } else {
                    $costs = self::parseCostLine($lines[$curLine + 2]);
                    $curLine += 3;
                }
                $entry = new Data\RawEntry($fl, $fn, $costs);
                $subCalls = 0;

                // Now check for subcalls
                while(isset($lines[$curLine]) && $lines[$curLine] != '') {
                    if (strncmp('cfn=', $lines[$curLine], 4) != 0) {
                        // This doesn't look like a call, panik
                        die("parse error on line {$curLine}. (Current line: {$lines[$curLine]}) (Script line: "
                            . __LINE__ . ")\n");
                    }
                    // $calleeName = substr($lines[$curLine], 4);
                	$entry->addCall( self::parseCallLine($lines[$curLine + 1]) );
                    $curLine += 3;
                    $subCalls++;
                }

                // Add this entry to the list.
               	$entries[] = $entry;
            }
            // Skip empty line between blocks
            $curLine += 1;
        }
        return $entries;
    }

    /*
     * Parses a call line.
     *
     * @return array Array with calls, meaning how often the call happened.
     */
    private static function parseCallLine($line)
    {
        $words = explode(' ', $line);
        return (int)substr($words[0], 6);
    }

    /*
     * Parses a cost line.
     *
     * @return array Associative array with line, time, mem,
     *               cycles and peakmem.
     */
    private static function parseCostLine($line)
    {
        $tokens = explode(' ', $line);
        return array(
            'time'    => $tokens[1],
            'mem'     => $tokens[2],
            'cycles'  => $tokens[3],
            'peakmem' => $tokens[4],
        );
    }
}
