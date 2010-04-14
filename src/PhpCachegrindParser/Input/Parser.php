<?php

/**
 * This file contains the class PhpCachegrindParser\Input\Parser.
 *
 * PHP version 5
 *
 * @author Kevin-Simon Kohlmeyer <simon.kohlmeyer@googlemail.com>
 */

namespace PhpCachegrindParser\Input;

require_once "Data/RawEntry.php";
require_once "Data/RawCall.php";
require_once "Data/CallTree.php";
use \PhpCachegrindParser\Data as Data;

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
     * Generates an array of entries.
     *
     * Each function block in the input file is represented by an object
     * in this array.
     *
     * @return array Array of PhpCachegrindParser\Entry objects.
     */
    public function getEntryList()
    {
        $lines = explode("\n", $this->inputData);
        // This makes our array indices the same as the file's line numbers
        array_unshift($lines, '');
        $curLine = 7; // The first 6 lines are metadata

        $entries = array(); // Here we'll store the generated entries.

        //TODO: More input validation here
        while($curLine + 1 < count($lines)) {
            if (strncmp($lines[$curLine], 'fl=', 3) != 0) {
                // Don't know what to do, panic
                die("parse error on line $curLine. (Script line: "
                    . __LINE__ . ")\n");
            } else {
                // Regular block
                // Strip fl= from file name and fn from funcname.
                $fl = substr($lines[$curLine], 3);
                $fn = substr($lines[$curLine + 1], 3);
                if (strcmp($fn, '{main}') == 0) {
                    //NOTE: Extract summary here when neccessary
                    $costs = self::parseCostLine($lines[$curLine + 5]);
                    $curLine += 6;
                } else {
                    $costs = self::parseCostLine($lines[$curLine + 2]);
                    $curLine += 3;
                }
                $entry = new Data\RawEntry($fl, $fn, $costs);

                // Now check for subcalls
                while(strcmp('',$lines[$curLine]) != 0) {
                    if (strncmp('cfn=', $lines[$curLine], 4) != 0) {
                        // This doesn't look like a call, panik
                        die("parse error on line $curLine. (Script line: "
                            . __LINE__ . ")\n");
                    }

                    $calleeName = substr($lines[$curLine], 4);
                    $callData = self::parseCallLine($lines[$curLine + 1]);
                    $costs = self::parseCostLine($lines[$curLine + 2]);
                    $call = new Data\RawCall($calleeName, $callData, $costs);

                    $entry->addCall($call);
                    $curLine += 3;
                }
                // Add this entry to the list.
                $entries[] = $entry;
            }
            // Skip empty line between blocks
            $curLine += 1;
        }
        return $entries;
    }

    /**
     * Returns the call tree.
     *
     * Note: If you need both the entry list and the call tree, add caching
     * to the Parser class.
     *
     * @return PhpCachegrindParser\Data\CallTree The calltree.
     */
    public function getCallTree()
    {

        $entries = array_reverse($this->getEntryList());

        $parent = array();
        $childrenLeft = array();

        // Add root to the parents stack
        $mainEntry = new Data\RawEntry("", "{root}", array());
        $root = Data\CallTree::fromRawEntry($mainEntry);
        array_push($parent, $root);
        array_push($childrenLeft, count($mainEntry->getSubcalls()));

        foreach($entries as $entry) {
            $node = Data\CallTree::fromRawEntry($entry);
            end($parent)->addChild($node);
            array_push($childrenLeft, array_pop($childrenLeft) -1);

            if(end($childrenLeft) == 0) {
                array_pop($parent);
                array_pop($childrenLeft);
            }
            $subcalls = count($entry->getSubcalls());
            if ($subcalls != 0) {
                array_push($parent, $node);
                array_push($childrenLeft, $subcalls);
            }
        }
        return $root;
    }

    /*
     * Parses a call line.
     *
     * @return array Array with calls, meaning how often the call happened.
     */
    private static function parseCallLine($line)
    {
        $words = explode(' ', $line);
        return array(
            'calls' => (int) substr($words[0], 6),
        );
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
