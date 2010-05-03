<?php

/**
 * This file contains the class CachegrindParser\Data\CallTreeNode.
 *
 * PHP version 5
 *
 * @author Kevin-Simon Kohlmeyer <simon.kohlmeyer@googlemail.com>
 */
use CachegrindParser\Data;
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
    private $path;

    private $costs;
    private $inclusiveCostsCache;

    private $children = array();
    private $parent;

    /** Tracks with how many siblings this node was combined. */
    private $count;

    private $costRatings;

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
        $this->path = $filename .$funcname;  
        $this->costs = $costs;
        $this->count = 1;
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
        if ( strpos( $child->path, $this->path . '//' ) !== 0 )
        	$child->path = $this->path . '//' . $child->path;
        
        $this->resetInclusiveCostsCache();
    }
    
    /**
     * Merges a new child node into the tree.
     * 
     * @param PhpCachegrind\Data\CallTreeNode $child The child node to be merged.
     */
    public function mergeChild(CallTreeNode $child)
    {
    	$candidate = $this->getChildByPath( $child->path );
    	
    	if ( $candidate != null ) {
    		
    		foreach ($child->children as $subChild) {
    			$candidate->mergeChild( $subChild );
    		} 
            $candidate->costs = self::combineCostArrays($child->costs, $candidate->costs);
        	$candidate->count += $child->count;

            // Reset references
            unset($child->parent);
            unset($child->children);

            // and candidate's cache
			$candidate->resetInclusiveCostsCache();
    	}
  		else {
  			// Reset references
  			unset($child->parent);

  			$this->addChild( $child );
  		}
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
                $inclCosts = self::combineCostArrays($inclCosts,
                                    $child->getInclusiveCosts());
            }
            $this->inclusiveCostsCache = $inclCosts;
        }
        return $this->inclusiveCostsCache;
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
        $this->costRatings = $ratings;
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
        assert(isset($this->costRatings));
        return $this->costRatings;
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
     * Returns the name of the node path.
     *
     * @return string File name.
     */
    public function getPath()
    {
        return $this->path;
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
        return array_values($this->children);
    }

    /**
     * Returns the first child that matches the path
     *
     * @return Data\CallTreeNode CallTreeNore or null if not found
     */
    public function getChildByPath( $path )
    {
        foreach ($this->children as $child) {
        	if ( $child->path == $path )
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
        return $this->count;
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
        // Confirm that we're our parent's child.
        //assert(in_array($this, $this->parent->children));

        $this->parent->costs = self::combineCostArrays($this->parent->costs,
                                                   $this->getInclusiveCosts());

        // strict: exact match, avoid nested loop error
        $idx = array_search($this, $this->parent->children, true);
        unset($this->parent->children[$idx]);
        unset($this->parent);
    }

    /**
     * Combines similar children.
     *
     * Here, similar means that they have the same file and function name.
     */
    public function combineSimilarChildren()
    {
        // re-key our children.
        $this->children = array_values($this->children);

        for ($i = count($this->children) - 1; $i >= 0; $i--) {
            $merged = false;
            $child = $this->children[$i];
            for ($j = $i - 1; $j >= 0 && $merged == false; $j--) {
                $candidate = $this->children[$j];
                if ($candidate->fn === $child->fn &&
                        $candidate->fl === $child->fl) {
                    // Merge child into candidate
                    // Combine the children and the costs.
                    $candidate->children = array_merge($candidate->children,
                                                       $child->children);
                    $candidate->costs = self::combineCostArrays($child->costs,
                                                            $candidate->costs);

                    $candidate->count += $child->count;

                    // Reset references
                    unset($this->children[$i]);
                    unset($child->parent);

                    // and candidate's cache
                    $candidate->resetInclusiveCostsCache();

                    // Go to the next child
                    $merged = true;
                }
            }
        }
        $this->children = array_values($this->children);
    }

    /*
     * Combines two costs arrays. Time and cycles will be added, mem
     * and peakmem will be the max of the two values.
     *
     * @param array $a1 The first cost array.
     * @param array $a2 The second cost array.
     * @return array A combined cost array.
     */
    private static function combineCostArrays($a1, $a2)
    {
        return array(
            'time'    => $a1['time']   + $a2['time'],
            'cycles'  => $a1['cycles'] + $a2['cycles'],
            'mem'     => max($a1['mem'], $a2['mem']),
            'peakmem' => max($a1['peakmem'], $a2['peakmem']),
        );
    }
}
