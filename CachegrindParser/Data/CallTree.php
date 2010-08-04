<?php

/**
 * This file contains the class CachegrindParser\Data\CallTreeNode.
 *
 * PHP version 5
 *
 * @author Kevin-Simon Kohlmeyer <simon.kohlmeyer@googlemail.com>
 * @author Thomas Bley <thomas.bley@mayflower.de>
 */

namespace CachegrindParser\Data;

use \CachegrindParser\Input as Input;

/**
 * This class represents a call tree.
 *
 * It stores the tree itself and the information given in the
 * cg file's summary line
 * Additionally, Filter objects can be added that can be used
 * to filter the call tree.
 */
class CallTree
{
    private $summary;
    private $root;

    /** Stores the filters we will use when parsing. */
    private $filters = array();

    /**
     * Creates a new CallTreeNode object with the given data.
     *
     * @param CachegrindParser\Data\CallTreeNode $rootNode The root node
     * @param array $summary An array containing: 'time'    => integer
     *                                            'cycles'  => integer
     *                                            'mem'     => integer
     *                                            'peakmem' => integer
     */
    function __construct(CallTreeNode $root, $summary)
    {
        $this->root     = $root;
        $this->summary  = $summary;
    }

    /**
     * Gets the root node.
     *
     * @return CachegrindParser\Data\CallTreeNode The calltree's root node.
     */
    public function getRoot()
    {
        return $this->root;
    }

    /**
     * Gets the summary.
     *
     * @return array An array containing: 'time'    => integer
     *                                    'cycles'  => integer
     *                                    'mem'     => integer
     *                                    'peakmem' => integer
     */
    public function getSummary()
    {
        return $this->summary;
    }

    /**
     * Compresses all subtrees. Note that you shouldn't add more children
     * to the tree after calling this.
     */
    public function combineSimilarSubtrees() {
        $queue = array($this->root);
        while ($queue) {
            $node = array_shift($queue);
            $node->combineSimilarChildren();
            foreach ($node->getChildren() as $child) {
                array_push($queue, $child);
            }
        }
    }

    /**
     * Merge a tree into the current tree
     *
     * @param CallTree $tree
     */
    public function combineTrees(CallTree $tree) {

        foreach ( $tree->getRoot()->getChildren() as $key=>$child ) {
            $this->getRoot()->mergeChild( $child );
        }

        $this->summary = self::combineSummaryArrays( $this->getSummary(), $tree->getSummary() );
    }

    /**
     * Filter the tree
     */
    public function filterTree() {
        foreach ($this->filters as $filter)
            $filter->filter( $this );
    }

    /**
     * Adds a filter to the parser.
     *
     * @param CachegrindParser\Input\Filter The filter.
     */
    public function addFilter(Input\Filter $filter)
    {
        $this->filters[] = $filter;
    }

    /*
     * Combines two summary arrays. Time and cycles will be added, mem
     * and peakmem will be the max of the two values.
     *
     * @param array $a1 The first cost array.
     * @param array $a2 The second cost array.
     * @return array A combined cost array.
     */
    public static function combineSummaryArrays($a1, $a2)
    {
        return array(
            'time'    => $a1['time']   + $a2['time'],
            'cycles'  => $a1['cycles'] + $a2['cycles'],
            'mem'     => max($a1['mem'], $a2['mem']),
            'peakmem' => max($a1['peakmem'], $a2['peakmem']),
        );
    }

}
