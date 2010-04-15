<?php

/**
 * This file contains the class CachegrindParser\Output\DotFormatter.
 *
 * PHP version 5
 *
 * @author Kevin-Simon Kohlmeyer <simon.kohlmeyer@googlemail.com>
 */

namespace CachegrindParser\Output;

/**
 * This class formats the call tree to the dot format.
 * (Mainly supposed to be used by graphviz' dot program)
 */
class DotFormatter implements Formatter
{
    /**
     * Implements format($parser) as declared in Formatter.
     */
    public function format($parser)
    {
        $root = $parser->getCallTree()->getRoot();

        $output  =<<<NOW
digraph {
node [shape=box];
rankdir=LR;

NOW;
        $output .= '"' . spl_object_hash($root) . '" [label="{root}"];' . "\n";

        // Breadth-first search
        $nodeQueue = array();
        array_push($nodeQueue, $root);
        while ($nodeQueue) {
            $parent = array_shift($nodeQueue);

            $parentName = '"' . $parent->getFuncname() . '"';
            $parentID = '"' . spl_object_hash($parent) . '"';

            foreach ($parent->getChildren() as $child) {
                $childID = '"' . spl_object_hash($child) . '"';
                array_push($nodeQueue, $child);
                $childName = htmlentities($child->getFuncname());
                $label =<<<NOW
<<table border='0'>
<tr><td colspan='2'>$childName</td></tr>
NOW;
                foreach ($child->getCosts() as $n => $v) {
                    $label .=<<<NOW
<tr>
<td align='right'>$n:</td>
<td align='left'>$v</td>
<td fixedsize='true' width='10' height='10' bgcolor='green'></td>
</tr>
NOW;
                }
                $label .= '</table>>';
                $output .= $childID . " [label=$label];\n";
                $output .= $parentID . '->' . $childID . ";\n";
            }
        }
        $output .= '}';
        return $output;
    }
}
