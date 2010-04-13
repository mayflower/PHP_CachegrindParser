<?php
namespace PhpCachegrindParser\Data;

class CallTree {

    private $fl;
    private $fn;

    private $costs;

    private $children = array();

    function __construct($filename, $funcname, $costs) {
        $this->fl = $filename;
        $this->fn = $funcname;
        $this->costs = $costs;
    }

    /**
     * Convenience function to get a CallTree from an RawEntry.
     */
    public static function fromRawEntry($entry) {
        return new CallTree($entry->getFilename(), $entry->getFuncname(), $entry->getCosts());
    }

    public function addChild(CallTree $child) {
        $this->children[] = $child;
    }

    public function getInclusiveCosts() {
        $c = $costs;

        foreach ($children as $child) {
            $c = self::mergeCosts($c, $child->getInclusiveCosts());
        }
        return $c;
    }

    /*
     * Merges two arrays.
     *
     * @return The merged array.
     */
    private static function mergeCosts($c1, $c2) {
        foreach($c2 as $k => $v) {
            $c1[$k] += $v;
        }
        return $c1;
    }
}
