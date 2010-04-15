<?php

/**
 * This file contains the class CachegrindParser\Input\CostsRating.
 *
 * PHP version 5
 *
 * @author Kevin-Simon Kohlmeyer <simon.kohlmeyer@googlemail.com>
 */

namespace CachegrindParser\Input;

/**
 * This class annotates a call tree with the severity of each node's costs.
 */
class CostsRating
{
    /**
     * This function traverses a tree and annotates all nodes with their cost's
     * severity.
     *
     * This function has no real meaning at this point, it's just a way to
     * get colored dots.
     * TODO: Change this.
     *
     * @param CachegrindParser\Data\CallTree $tree The tree to work on.
     */
    public function rateCosts(\CachegrindParser\Data\CallTree &$tree)
    {
        $nodeQueue = $tree->getRoot()->getChildren();
        $total = $tree->getSummary();

        while($nodeQueue) {
            $node = array_pop($nodeQueue);
            $ratings = array();
            $costs = $node->getCosts();

            foreach ($costs as $k => $v) {
                if ($v == 0) {
                    $ratings[$k] = 0;
                } else {
                    $part = $v / $total[$k];
                    if ($part >= 0.05) {
                        $ratings[$k] = 1;
                    } else {
                        $ratings[$k] = 20.0 * ($v / $total[$k]);
                    }
                }
            }
            $node->setCostRatings($ratings);
            foreach ($node->getChildren() as $child) {
                array_push($nodeQueue, $child);
            }
        }
    }
}
