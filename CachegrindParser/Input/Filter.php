<?php

/**
 * This file contains the class CachegrindParser\Input\Filter.
 *
 * PHP version 5
 *
 * @author Kevin-Simon Kohlmeyer <simon.kohlmeyer@googlemail.com>
 */

namespace CachegrindParser\Input;

require_once "CachegrindParser/Data/CallTree.php";

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
     * @param CachegrindParser\Data\CallTree The tree to filter.
     */
    public function applyFilter(\CacheGrindParser\Data\CallTree &$param);
}
