<?php

/**
 * This file contains the class CachegrindParser\Input\DepthFilter
 *
 * PHP version 5
 *
 * @author Kevin-Simon Kohlmeyer <simon.kohlmeyer@googlemail.com>
 */

namespace CachegrindParser\Input;

require_once 'CachegrindParser/Input/Filter.php';

/**
 * This class implements an input filter.
 *
 * It removes all calls that are more than a few calls from the root node.
 */
class DepthFilter implements Filter
{

    private $_depth;

    /**
     * Creates a new DepthFilter instance.
     *
     * @param integer $depth The number of levels after which the graph
     *                       should be pruned.
     */
    function __construct($depth)
    {
        $this->_depth = $depth;
    }

    /**
     * Implements filter as defined in the interface Filter.
     */
    public function applyFilter(\CachegrindParser\Data\Calltree &$tree)
    {
        $queue = array($tree->getRoot());
        $queueNext = array();
        $curDepth = 0; // Depth of elements in the queue we're working on.

        $current =& $queue;
        $next    =& $queueNext;
        do {
            if ($curDepth < $this->_depth) {
                while ($current) {
                    foreach (array_pop($current)->getChildren() as $child) {
                        array_push($next, $child);
                    }
                }
            } else {
                // Reached limit.
                while ($current) {
                    array_pop($current)->mergeIntoParent();
                }
            }
            $tmp      =& $current;
            $current  =& $next;
            $next     =& $tmp;
            $curDepth += 1;
        } while ($current);
    }
}

