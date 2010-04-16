<?php

/**
 * This file contains the class CachegrindParser\Output\DotFormatter.
 *
 * PHP version 5
 *
 * @author Kevin-Simon Kohlmeyer <simon.kohlmeyer@googlemail.com>
 */

namespace CachegrindParser\Output;

require_once 'CachegrindParser/Input/CostsRating.php';
use CachegrindParser\Input\CostsRating;

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
        $tree = $parser->getCallTree();
        $tree->combineSimilarSubtrees();
        $rating = new CostsRating();
        $rating->rateCosts($tree);

        $root = $tree->getRoot();

        $output  = "digraph {\nnode [shape=box];\n";
        $output .= "rankfir=LR;\n";
        $output .= '"' . spl_object_hash($root) . '" [label="{root}"];' . "\n";

        $nodeQueue = array();
        array_push($nodeQueue, $root);
        while ($nodeQueue) {
            $parent = array_shift($nodeQueue);

            $parentName = '"' . $parent->getFuncname() . '"';
            $parentID = '"' . spl_object_hash($parent) . '"';

            foreach ($parent->getChildren() as $child) {
                $childID = '"' . spl_object_hash($child) . '"';

                // Add the child's node
                $output .= $childID . ' [label=' . self::label($child) . "];\n";
                // And the edge
                $output .= $parentID . '->' . $childID . ";\n";

                array_push($nodeQueue, $child);
            }
        }
        $output .= '}';
        return $output;
    }

    /**
     * Generates a label for the given node.
     *
     * @param CachegrindParser\Data\CallTreeNode $node The node to generate
     *                                                 a label for.
     * @return string A label for the given node.
     */
    private static function label(\CachegrindParser\Data\CallTreeNode $node) {
        $nodeName = htmlentities($node->getFuncname());
        $nodeFile = htmlentities($node->getFilename());
        $label  = "<<table border='0'>\n";
        $label .= "<tr><td colspan='2'>$nodeFile</td></tr>";
        $label .= "<tr><td colspan='2'>$nodeName</td></tr>";
        $ratings = $node->getCostRatings();

        foreach ($node->getCosts() as $n => $v) {
            $label .= '<tr>';
            $label .= "<td align='right'>$n:</td>\n";
            $label .= "<td align='left'>$v</td>\n";
            $label .= "<td fixedsize='true' width='10' height='10' ";
            $label .= "bgcolor='";
            $label .= self::colorFromRating($ratings[$n]) . "'></td>\n</tr>\n";
        }
        $label .= '</table>>';

        return $label;
    }

    /**
     * Generates a color from a Rating between 0 and 1 inclusive.
     *
     * @param float $rating The rating.
     * @return string A colorstring to be used in the dot file.
     */
    private static function colorFromRating($rating)
    {
        if ($rating < 0.8) {
            return 'green';
        } else if ($rating < 0.9) {
            return 'yellow';
        } else {
            return 'red';
        }
    }
}
