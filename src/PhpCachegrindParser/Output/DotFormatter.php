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
        $callTree = $parser->getCallTree();

        $output  = "digraph {\n";
        $output .= "node [shape=box];\n";
        $output .= "rankdir=LR;\n";
        $output .= '"' . spl_object_hash($callTree)
                       . "\" [label=\"{root}\"];\n"; 

        $nodeQueue = array();
        array_push($nodeQueue, $callTree);
        while ($nodeQueue) {
            $parent = array_shift($nodeQueue);

            $parentName = '"' . $parent->getFuncname() . '"';
            $parentID = '"' . spl_object_hash($parent) . '"';
            foreach ($parent->getChildren() as $child) {
                if (strcmp($child->getFilename(), 'php:internal') == 0) {
                    continue;
                }
                $childID = '"' . spl_object_hash($child) . '"';
                array_push($nodeQueue, $child);
                $output .= $childID . " [label=\"{$child->getFuncname()}\"];\n";
                $output .= $parentID . '->' . $childID . ";\n";
            }
        }
        $output .= '}';
        return $output;
    }
}
