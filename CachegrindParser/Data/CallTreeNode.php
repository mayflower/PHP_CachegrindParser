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

/**
 * This class represents a node in the call tree.
 *
 * It contains the name of the function/method, the file it was defined in,
 * it's costs and a CallTreeNode object for each function it called.
 */
class CallTreeNode
{

    private $_fl;
    private $_fn;
    private $_path;

    private $_costs;
    private $_inclusiveCostsCache;

    /** Tracks with how many siblings this node was combined. */
    private $_count;

    private $_costRatings;

    private $_children = array();
    private $_parent;

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
        $this->_fl = $filename;
        $this->_fn = $funcname;
        // without filename, less memory usage
        $this->_path = basename($filename) . $funcname;
        $this->_costs = $costs;
        $this->_count = 1;
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
        assert(!isset($child->_parent));
        $this->_children[] = $child;
        $child->_parent = $this;
        if (strpos($child->_path, $this->_path . '//') !== 0)
            $child->_path = $this->_path . '//' . $child->_path;

        $this->resetInclusiveCostsCache();
    }

    /**
     * Merges a new child node into the tree.
     *
     * @param PhpCachegrind\Data\CallTreeNode $child The child node to
     *        be merged.
     */
    public function mergeChild(CallTreeNode $child)
    {
        $candidate = $this->getChildByPath($child->_path);

        if ($candidate != null) {

            foreach ($child->_children as $subChild) {
                $candidate->mergeChild($subChild);
            }
            $candidate->_costs = self::combineCostArrays(
                $child->_costs, $candidate->_costs
            );
            $candidate->_count += $child->_count;

            // and candidate's cache
            $candidate->resetInclusiveCostsCache();
        } else {
              // Reset references
              unset($child->_parent);

              $this->addChild($child);
        }
    }

    /*
     * Resets the inclusive Costs cache.
     */
    private function resetInclusiveCostsCache()
    {
        if (isset($this->_inclusiveCostsCache)) {
            unset($this->_inclusiveCostsCache);
            if ($this->_parent) {
                $this->_parent->resetInclusiveCostsCache();
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
        return $this->_costs;
    }

    /**
     * Returns the costs of this call plus the inclusive
     * costs of all functions called by this one.
     *
     * @return integer The functions inclusive costs.
     */
    public function getInclusiveCosts()
    {
        if (!isset($this->_inclusiveCostsCache)) {
            $inclCosts = $this->_costs;

            foreach ($this->_children as $child) {
                $inclCosts = self::combineCostArrays(
                    $inclCosts,
                    $child->getInclusiveCosts()
                );
            }
            $this->_inclusiveCostsCache = $inclCosts;
        }
        return $this->_inclusiveCostsCache;
    }

    /**
     * Set the cost ratings
     *
     * @param array $ratings Array with numbers between 0 and 1 inclusive that
     *                       indicate how bad the values in the costs array are.
     *                       1 is the worst, 0 the best possible value.
     *                       Must contain the same keys as costs.
     */
    public function setCostRatings($ratings)
    {
        $this->_costRatings = $ratings;
    }

    /**
     * Retrieves an array containing the ratings for each cost.
     * Should only be called after the ratings have been set.
     *
     * @return array Array with numbers between 0 and 1 inclusive indicating
     *               how bad the single costs of this node are, with 1 being the
     *               most worst.
     */
    public function getCostRatings()
    {
        assert(isset($this->_costRatings));
        return $this->_costRatings;
    }

    /**
     * Returns the name of the file this function is located in.
     *
     * @return string File name.
     */
    public function getFilename()
    {
        return $this->_fl;
    }

    /**
     * Returns the name of this function.
     *
     * @return string Function name.
     */
    public function getFuncname()
    {
        return $this->_fn;
    }

    /**
     * Returns the name of the node path.
     *
     * @return string File name.
     */
    public function getPath()
    {
        return $this->_path;
    }


    /**
     * Returns the children of this node.
     *
     * @return array Array with CachegrindParser\Data\CallTreeNode
     *               objects that are called by this function.
     */
    public function getChildren()
    {
        // We might have holes in our array
        return array_values($this->_children);
    }

    /**
     * Returns the first child that matches the path
     *
     * @return Data\CallTreeNode CallTreeNore or null if not found
     */
    public function getChildByPath( $path )
    {
        foreach ($this->_children as $child) {
            if ($child->_path == $path)
                return $child;
        }
        return null;
    }

    /**
     * Returns the number of subtrees this node represents.
     *
     * @return integer The call count of this node.
     */
    public function getCallCount()
    {
        return $this->_count;
    }

    /**
     * Merges this node into it's parent.
     *
     * The inclusive costs of this node are added to the parent's costs,
     * and this node is removed from the children of it's parent.
     */
    public function mergeIntoParent()
    {
        assert($this->_parent); // Make sure we're not the root node.
        // Confirm that we're our parent's child.
        //assert(in_array($this, $this->_parent->_children));

        $this->_parent->_costs = self::combineCostArrays(
            $this->_parent->_costs,
            $this->getInclusiveCosts()
        );

        // mark deleted node as dropped (unset does not always work ...)
        $this->_fn = 'dropped';

        // strict: exact match, avoid nested loop error
        $idx = array_search($this, $this->_parent->_children, true);

        unset($this->_parent->_children[$idx]);
        unset($this->_parent);
    }

    /**
     * Combines similar children.
     *
     * Here, similar means that they have the same file and function name.
     */
    public function combineSimilarChildren()
    {
        // re-key our children.
        $this->_children = array_values($this->_children);

        for ($i = count($this->_children) - 1; $i >= 0; $i--) {
            $merged = false;
            $child = $this->_children[$i];
            for ($j = $i - 1; $j >= 0 && $merged == false; $j--) {
                $candidate = $this->_children[$j];
                if ($candidate->_fn === $child->_fn &&
                    $candidate->_fl === $child->_fl) {
                    // Merge child into candidate
                    // Combine the children and the costs.
                    $candidate->_children = array_merge(
                        $candidate->_children,
                        $child->_children
                    );
                    $candidate->_costs = self::combineCostArrays(
                        $child->_costs,
                        $candidate->_costs
                    );

                    $candidate->_count += $child->_count;

                    // Reset references
                    unset($this->_children[$i]);
                    unset($child->_parent);

                    // and candidate's cache
                    $candidate->resetInclusiveCostsCache();

                    // Go to the next child
                    $merged = true;
                }
            }
        }
        $this->_children = array_values($this->_children);
    }

    /*
     * Combines two costs arrays. Time and cycles will be added, mem
     * and peakmem will be the max of the two values.
     *
     * @param array $first The first cost array.
     * @param array $second The second cost array.
     * @return array A combined cost array.
     */
    private static function combineCostArrays($first, $second)
    {
        return array(
            'time'    => $first['time']   + $second['time'],
            'cycles'  => $first['cycles'] + $second['cycles'],
            'mem'     => max($first['mem'], $second['mem']),
            'peakmem' => max($first['peakmem'], $second['peakmem']),
        );
    }
}
