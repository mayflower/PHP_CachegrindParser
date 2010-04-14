<?php

/**
 * This file contains the class PhpCachegrindParser\Data\RawEntry.
 *
 * PHP version 5
 *
 * @author Kevin-Simon Kohlmeyer <simon.kohlmeyer@googlemail.com>
 */

namespace PhpCachegrindParser\Data;

/**
 * Represents a function block in the input file.
 */
class RawEntry
{

    private $fl;
    private $fn;

    private $costs;

    private $subcalls = array();

    function __construct($filename, $funcname, $costs)
    {
        $this->fl = $filename;
        $this->fn = $funcname;

        $this->costs = $costs;
    }

    /**
     * Add a subcall to this entry.
     */
    public function addCall(RawCall $call)
    {
        $this->subcalls[] = $call;
    }

    public function getFilename()
    {
        return $this->fl;
    }

    public function getFuncname()
    {
        return $this->fn;
    }

    public function getCosts()
    {
        return $this->costs;
    }

    public function getSubcalls()
    {
        return $this->subcalls;
    }
}

