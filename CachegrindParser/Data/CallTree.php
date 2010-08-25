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
    private $_summary;
    private $_root;

    /** Stores the filters we will use when parsing. */
    private $_filters = array();

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
        $this->_root     = $root;
        $this->_summary  = $summary;
    }

    /**
     * Gets the root node.
     *
     * @return CachegrindParser\Data\CallTreeNode The calltree's root node.
     */
    public function getRoot()
    {
        return $this->_root;
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
        return $this->_summary;
    }

    /**
     * Compresses all subtrees. Note that you shouldn't add more children
     * to the tree after calling this.
     */
    public function combineSimilarSubtrees()
    {
        $queue = array($this->_root);
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
    public function combineTrees(CallTree $tree)
    {

        foreach ($tree->getRoot()->getChildren() as $child) {
            $this->getRoot()->mergeChild($child);
        }

        $this->_summary = self::combineSummaryArrays(
            $this->getSummary(), $tree->getSummary()
        );
    }

    /**
     * Filter the tree
     */
    public function filterTree()
    {
        foreach ($this->_filters as $filter)
            $filter->filter($this);
    }

    /**
     * Adds a filter to the parser.
     *
     * @param CachegrindParser\Input\Filter The filter.
     */
    public function addFilter(Input\Filter $filter)
    {
        $this->_filters[] = $filter;
    }

    /*
     * Combines two summary arrays. Time and cycles will be added, mem
     * and peakmem will be the max of the two values.
     *
     * @param array $first The first cost array.
     * @param array $second The second cost array.
     * @return array A combined cost array.
     */
    public static function combineSummaryArrays($first, $second)
    {
        return array(
            'time'    => $first['time']   + $second['time'],
            'cycles'  => $first['cycles'] + $second['cycles'],
            'mem'     => max($first['mem'], $second['mem']),
            'peakmem' => max($first['peakmem'], $second['peakmem']),
        );
    }

}
