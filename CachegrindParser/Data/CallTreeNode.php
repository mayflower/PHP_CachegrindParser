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
 * This class represents a node in the call tree.
 *
 * It contains the name of the function/method, the file it was defined in,
 * it's costs and a CallTreeNode object for each function it called.
 */
class CallTreeNode
{

    private $fl;
    private $fn;

    private $costs;
    private $inclusiveCostsCache;

    private $children = array();
    private $parent;

    /**
     * Creates a new CallTreeNode object with the given values
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
     * Convenience function to get a CallTreeNode from an RawEntry.
     */
    public static function fromRawEntry($entry)
    {
        return new CallTreeNode($entry->getFilename(),
                                $entry->getFuncname(),
                                $entry->getCosts());
    }

    /**
     * Adds a subcall to this node.
     *
     * @param PhpCachegrind\Data\CallTreeNode $child The child node to add.
     */
    public function addChild(CallTreeNode $child)
    {
        assert(!isset($child->parent));
        $this->children[] = $child;
        $child->parent = $this;

        $this->resetInclusiveCostsCache();
    }

    /*
     * Resets the inclusive Costs cache.
     */
    private function resetInclusiveCostsCache() {
        if (isset($this->inclusiveCostsCache)) {
            unset($this->inclusiveCostsCache);
            if ($this->parent) {
                $this->parent->resetInclusiveCostsCache();
            }
        }
    }

    /**
     * Returns the costs of this entry.
     *
     * @return array  $costs Array with: 'time'    => integer
     *                                   'mem'     => integer
     *                                   'cycles'  => integer
     *                                   'peakmem' => integer
     */
    public function getCosts()
    {
        return $this->costs;
    }

    /**
     * Returns the costs of this call plus the inclusive
     * costs of all functions called by this one.
     *
     * @return integer The functions inclusive costs.
     */
    public function getInclusiveCosts()
    {
        if (!isset($this->inclusiveCostsCache)) {
            $inclCosts = $this->costs;

            foreach ($this->children as $child) {
                $childInclCosts = $child->getInclusiveCosts();
                $inclCosts['time']   += $childInclCosts['time'];
                $inclCosts['cycles'] += $childInclCosts['cycles'];
                $inclCosts['mem']     = max($inclCosts['mem'],
                                            $childInclCosts['mem']);
                $inclCosts['peakmem'] = max($inclCosts['peakmem'],
                                            $childInclCosts['peakmem']);
            }
            $this->inclusiveCostsCache = $inclCosts;
        }
        print_r($this->inclusiveCostsCache);
        return $this->inclusiveCostsCache;
    }

    /**
     * Returns the name of the file this function is located in.
     *
     * @return string File name.
     */
    public function getFilename()
    {
        return $this->fl;
    }

    /**
     * Returns the name of this function.
     *
     * @return string Function name.
     */
    public function getFuncname()
    {
        return $this->fn;
    }

    /**
     * Returns the children of this node.
     *
     * @return array Array with CachegrindParser\Data\CallTreeNode
     *               objects that are called by this function.
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * Merges this node into it's parent.
     *
     * The inclusive costs of this node are added to the parent's costs,
     * and this node is removed from the children of it's parent.
     */
    public function mergeIntoParent()
    {
        assert($this->parent); // Make sure we're not the root node.

        $pc = $this->parent->costs;
        $ic = $this->getInclusiveCosts();
        $pc['time']    = $ic['time'];
        $pc['cycles']  = $ic['cycles'];
        $pc['mem']     = max($pc['mem'], $ic['mem']);
        $pc['peakmem'] = max($pc['mem'], $ic['peakmem']);

        $idx = array_search($parent->children, $this);
        assert($idx); // Confirm that we're our parent's child.
        unset($parent->children[$idx]);
    }
}
