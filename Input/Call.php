<?php
namespace PhpCachegrindParser\Input;

class Call {
    private $cfn;

    private $time;
    private $mem;
    private $cycles;
    private $peakmem;

    function __construct($funcname, $callData, $costs) {
        $this->cfn = $funcname;
        $this->calls = $callData['calls'];

        $this->time    = $costs['time'];
        $this->mem     = $costs['mem'];
        $this->cycles  = $costs['cycles'];
        $this->peakmem = $costs['peakmem'];
    }
}

