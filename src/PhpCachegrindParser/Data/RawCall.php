<?php
namespace PhpCachegrindParser\Data;

class RawCall {
    private $cfn;

    private $calls;
    private $costs;

    function __construct($funcname, $callData, $costs) {
        $this->cfn = $funcname;
        $this->calls = $callData['calls'];

        $this->costs = $costs;
    }

    public function getFuncname() {return $this->cfn;}
    public function getCosts() {return $this->costs;}
    public function getCalls() {return $this->calls;}
}

