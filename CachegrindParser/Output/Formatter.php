<?php

/**
 * This file contains the interface CachegrindParser\Output\Formatter.
 *
 * PHP version 5
 *
 * @author Kevin-Simon Kohlmeyer <simon.kohlmeyer@googlemail.com>
 */

namespace CachegrindParser\Output;

/**
 * Interface that output formatters must implement.
 */
interface Formatter
{
    /**
     * Formats the Data provided by the given parser.
     *
     * @param CachegrindParser\Input\Parser parser The parser that
     *                                                will provide the data.
     * @return string The formatted data.
     */
    public function format($parser);
}
