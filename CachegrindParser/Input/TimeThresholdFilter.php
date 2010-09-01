<?php

/**
 * This file contains the class CachegrindParser\Input\TimeThresholdFilter
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
 * It removes all calls that make up for less than a given percentage
 * of the total time of the tree.
 */
class TimeThresholdFilter implements Filter
{

    private $_percentage;

    /**
     * Creates a new TimeTreshold instance.
     *
     * @param float $percentage Calls will be removed if they take less than
     *                            this percentage of the total costs.
     */
    function __construct($percentage)
    {
        assert($percentage <= 1 && $percentage >= 0);
        $this->_percentage = $percentage;
    }

    /**
     * Implements filter as defined in the interface Filter.
     */
    public function applyFilter(\CachegrindParser\Data\Calltree &$tree)
    {
        $queue = array($tree->getRoot());

        $queue = $tree->getRoot()->getChildren();
        $summary = $tree->getSummary();
        $minTime = $this->_percentage * $summary['time'];

        while ($queue) {
            $node = array_pop($queue);
            $costs = $node->getInclusiveCosts();
            if ($costs['time'] < $minTime) {
                $node->mergeIntoParent();
            } else {
                foreach ($node->getChildren() as $child) {
                    array_push($queue, $child);
                }
            }
        }
    }
}

