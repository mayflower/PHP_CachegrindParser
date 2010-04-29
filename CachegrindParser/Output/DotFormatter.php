<?php

/**
 * This file contains the class CachegrindParser\Output\DotFormatter.
 *
 * PHP version 5
 *
 * @author Kevin-Simon Kohlmeyer <simon.kohlmeyer@googlemail.com>
 * @author Thomas Bley <thomas.bley@mayflower.de>
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
    	$rootInclCosts = $root->getInclusiveCosts();
    	$rootTotalTime = $rootInclCosts['time'];

        $output  = "digraph {\nnode [shape=box,style=rounded];\nedge [color=orange];\n";
        $output .= '"' . spl_object_hash($root) . '" [label="{root}"];' . "\n";

        $nodeQueue = array();
        array_push($nodeQueue, $root);
        while ($nodeQueue) {
            $parent = array_shift($nodeQueue);

            $parentName = '"' . $parent->getFuncname() . '"';
            $parentID = '"' . spl_object_hash($parent) . '"';

            foreach ($parent->getChildren() as $child) {
                $childID = '"' . spl_object_hash($child) . '"';

    			$childInclCosts = $child->getInclusiveCosts();
    			$childTotalTime = $childInclCosts['time'];
                
    			$penWidth = max( 1, ceil(($childTotalTime / $rootTotalTime) * 10)); // thickness of edge
                
                // Add the child's node
                $output .= $childID . ' [label=' . self::label($child) . "];\n";
                // And the edge
                $output .= $parentID . '->' . $childID;
                $output .= ' [label=' . self::edgeLabel($child) . ",penwidth={$penWidth}];\n";

                array_push($nodeQueue, $child);
            }
        }
        $output .= '}';
        return $output;
    }

    /**
     * Generates a label to put on the edge to the given node.
     *
     * @param CachegrindParser\Data\CallTreeNode $node The node.
     *
     * @return string The label to put on the edge to $node.
     */
    private static function edgeLabel(\CachegrindParser\Data\CallTreeNode $node)
    {
    	$inclusiveCosts = $node->getInclusiveCosts();
    	$label =  $node->getCallCount() . 'x';
    	
    	if ( !empty( $inclusiveCosts['time'] ) )
    		$label .= ' [' . round($inclusiveCosts['time']/1000) . ' ms]';
    		    		
        return '"' . $label . '"';
    }

    /**
     * Generates a label for the given node.
     *
     * @param CachegrindParser\Data\CallTreeNode $node The node to generate
     *                                                 a label for.
     * @return string A label for the given node.
     */
    private static function label(\CachegrindParser\Data\CallTreeNode $node)
    {
        $nodeName = htmlentities($node->getFuncname());
        $nodeFile = htmlentities($node->getFilename());

        // Format nodeName #{20}...#{12}
        if ( strlen( $nodeName ) > 35 )
        	$nodeName = substr( $nodeName, 0, 20 ) . '...' . substr( $nodeName, -12 );
        
        // Format nodeFile ...#{32}
        if ( strlen( $nodeFile ) > 35 )
        	$nodeFile = '...' . substr( $nodeFile, -32 );

        $label  = "<<table border='0'>\n";
        $label .= "<tr><td border='0' align='center' bgcolor='lightblue'>{$nodeFile}<br/>";
        $label .= "{$nodeName}</td></tr>";

        $ratings = $node->getCostRatings();
        $costs = $node->getCosts();
        ksort($costs);
        $inclusiveCosts = $node->getInclusiveCosts();
        ksort($inclusiveCosts);

        $label .= '<tr><td><table border="0">';
        $label .= '<tr><td align="right">Incl. Costs</td><td></td>';
        $label .= '<td align="left">Own Costs</td></tr>\n';
        foreach ($costs as $n => $v) {
        	$bgcolor = self::colorFromRating($ratings[$n]);
        	
            $label .= "<tr>";
            $label .= "<td align='right' bgcolor='{$bgcolor}'>{$inclusiveCosts[$n]}</td>\n";
            $label .= "<td align='center' bgcolor='{$bgcolor}'>{$n}</td>\n";
            $label .= "<td align='right' bgcolor='{$bgcolor}'>{$v}</td>\n";
            // $label .= "<td fixedsize='true' width='10' height='10' bgcolor='{$bgcolor}'></td>\n
            $label .= "</tr>\n";
        }
        $label .= '</table></td></tr>';
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
            return 'lightgreen';
        } else if ($rating < 0.9) {
            return 'yellow';
        } else {
            return 'red';
        }
    }
}
