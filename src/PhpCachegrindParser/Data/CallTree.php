<?php

/**
 * This file contains the class PhpCachegrindParser\Data\CallTree.
 *
 * PHP version 5
 *
 * @author Kevin-Simon Kohlmeyer <simon.kohlmeyer@googlemail.com>
 */

namespace PhpCachegrindParser\Data;

/**
 * This class represents a node in the call tree.
 *
 * It contains the name of the function/method, the file it was defined in,
 * it's costs and a CallTree object for each function it called.
 */
class CallTree
{

    private $fl;
    private $fn;

    private $costs;
    private $inclusiveCostsCache;

    private $children = array();

    /**
     * Creates a new CallTree object with the given values
     *
     * @param string $filename The filename.
     * @param string $funcname The function name.
     * @param array  $costs Array with: 'time'    => integer
     *                                  'mem'     => integer
     *                                  'cycles'  => integer
     *                                  'peakmem' => integer
     */
    function __construct($filename, $funcname, $costs)
    {
        $this->fl = $filename;
        $this->fn = $funcname;
        $this->costs = $costs;
    }

    /**
     * Convenience function to get a CallTree from an RawEntry.
     */
    public static function fromRawEntry($entry)
    {
        return new CallTree($entry->getFilename(),
                            $entry->getFuncname(),
                            $entry->getCosts());
    }

    /**
     * Adds a subcall to this node.
     *
     * @param PhpCachegrind\Data\CallTree $child The child node to add.
     */
    public function addChild(CallTree $child)
    {
        $this->children[] = $child;
    }

    /**
     * Returns the costs of this call plus the inclusive
     * costs of all functions called by this one.
     *
     * @return integer The functions inclusive costs.
     */
    public function getInclusiveCosts()
    {
        if (!$this->inclusiveCostsCache) {
            $c = $costs;

            foreach ($children as $child) {
                $c = self::mergeCosts($c, $child->getInclusiveCosts());
            }
            $this->inclusiveCostsCache = $c;
        }
        return $inclusiveCostsCache;
    }

    /**
     * Returns the name of the file this function is located in.
     *
     * @return string File name.
     */
    public function getFilename() {
        return $this->fl;
    }

    /**
     * Returns the name of this function.
     *
     * @return string Function name.
     */
    public function getFuncname() {
        return $this->fn;
    }

    /**
     * Returns the children of this node.
     *
     * @return array Array with PhpCachegrindParser\Data\CallTree objects that
     *               are called by this function.
     */
    public function getChildren() {
        return $this->children;
    }

    /*
     * Merges two arrays.
     *
     * @return The merged array.
     */
    private static function mergeCosts($c1, $c2)
    {
        foreach($c2 as $k => $v) {
            $c1[$k] += $v;
        }
        return $c1;
    }
}
