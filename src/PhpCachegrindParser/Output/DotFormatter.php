<?php

/**
 * This file contains the class PhpCachegrindParser\Output\DotFormatter.
 *
 * PHP version 5
 *
 * @author Kevin-Simon Kohlmeyer <simon.kohlmeyer@googlemail.com>
 */

namespace PhpCachegrindParser\Output;

class DotFormatter implements Formatter
{
    public function format($parser)
    {
        $parser->getCallTree();
        return "blub";
    }
}
