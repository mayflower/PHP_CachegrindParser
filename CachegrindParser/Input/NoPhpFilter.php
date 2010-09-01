<?php

/**
 * This file contains the class CachegrindParser\Input\NoPhpFilter.
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
 * It removes all calls to functions that are internal to php.
 */
class NoPhpFilter implements Filter
{

    public function applyFilter(\CachegrindParser\Data\Calltree &$tree)
    {
        $nodeQueue = array($tree->getRoot());

        while ($nodeQueue) {
            $node = array_pop($nodeQueue);

            foreach ($node->getChildren() as $child) {
                if (strcmp($child->getFilename(), 'php:internal') == 0) {
                    $child->mergeIntoParent();
                } else {
                    array_push($nodeQueue, $child);
                }
            }
        }
    }
}

