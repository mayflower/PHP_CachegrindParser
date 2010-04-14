<?php

/**
 * This file contains the class PhpCachegrindParser\Input\Filter.
 *
 * PHP version 5
 *
 * @author Kevin-Simon Kohlmeyer <simon.kohlmeyer@googlemail.com>
 */

namespace PhpCachegrindParser\Input;

/**
 * This interface specifies a filter that the parser can use to trim
 * the call tree.
 */
interface Filter
{
    /**
     * Filters the given tree.
     *
     * @param PhpCachegrindParser\Data\CallTree The tree to filter.
     */
    public function filter($param);
}
