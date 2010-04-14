<?php
namespace PhpCachegrindParser\Input;

/**
 * Represents a function block in the input file.
 */
class Entry {

    private $fl;
    private $fn;

    private $time;
    private $mem;
    private $cycles;
    private $peakmem;

    private $subcalls = array();

    function __construct($filename, $funcname, $costs) {
        $this->fl = $filename;
        $this->fn = $funcname;

        $this->time    = $costs['time'];
        $this->mem     = $costs['mem'];
        $this->cycles  = $costs['cycles'];
        $this->peakmem = $costs['peakmem'];
    }

    /**
     * Add a subcall to this entry.
     */
    public function addCall(Call $call) {
        $this->subcalls[] = $call;
    }
}

