<?php

/**
 * This file contains the class PhpCachegrindParser\Input\Filter.
 *
 * PHP version 5
 *
 * @author Kevin-Simon Kohlmeyer <simon.kohlmeyer@googlemail.com>
 */

namespace PhpCachegrindParser\Input;

require_once "Data/CallTree.php";

/**
 * Interface for filters that the parser can use to trim the call tree.
 *
 * It is the filter's responsibility to preserve all costs, which means that
 * when a subtree is removed, it's inclusive costs have to be added to the
 * parent of the subtree.
 *
 * A CallTreeNode's mergeIntoParent() function can be used for this.
 */
interface Filter
{
    /**
     * Filters the given tree.
     *
     * @param PhpCachegrindParser\Data\CallTree The tree to filter.
     */
    abstract public function filter(\PhpCacheGrindParser\Data\CallTree $param);
}
