<?php

/**
 * This file contains the class CachegrindParser\Input\IncludeFilter.
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
 * It removes all calls to include, require, include_once
 * and require_once functions
 */
class IncludeFilter implements Filter
{

    public function applyFilter(\CachegrindParser\Data\Calltree &$tree)
    {
        $nodeQueue = array($tree->getRoot());

        while ($nodeQueue) {
            $node = array_pop($nodeQueue);

            foreach ($node->getChildren() as $child) {
                $func = $child->getFuncname();
                if (strncmp($func, 'include::', 9) == 0
                        || strncmp($func, 'require::', 9) == 0
                        || strncmp($func, 'include_once::', 14) == 0
                        || strncmp($func, 'require_once::', 14) == 0) {
                    $child->mergeIntoParent();
                } else {
                    array_push($nodeQueue, $child);
                }
            }
        }
    }
}

