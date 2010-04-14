<?php
namespace PhpCachegrindParser\Output;

interface Formatter
{
    /**
     * Formats the Data provided by the given parser.
     *
     * @param parser The parser that will provide the data.
     * @return The formatted data as a string.
     */
    public function format($parser);
}
