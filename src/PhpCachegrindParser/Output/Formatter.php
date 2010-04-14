<?php

/**
 * This file contains the interface PhpCachegrindParser\Output\Formatter.
 *
 * PHP version 5
 *
 * @author Kevin-Simon Kohlmeyer <simon.kohlmeyer@googlemail.com>
 */

namespace PhpCachegrindParser\Output;

/**
 * Interface that output formatters must implement.
 */
interface Formatter
{
    /**
     * Formats the Data provided by the given parser.
     *
     * @param PhpCachegrindParser\Input\Parser parser The parser that
     *                                                will provide the data.
     * @return string The formatted data.
     */
    public function format($parser);
}
