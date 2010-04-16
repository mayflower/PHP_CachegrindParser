<?php

/**
 * This file contains the class CachegrindParser\Data\CallTreeNode.
 *
 * PHP version 5
 *
 * @author Kevin-Simon Kohlmeyer <simon.kohlmeyer@googlemail.com>
 */

namespace CachegrindParser\Data;

/**
 * This class represents a call tree.
 *
 * It stores the tree itself and the information given in the
 * cg file's summary line
 */
class CallTree
{
    private $root;
    private $summary;

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
}
