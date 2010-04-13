<?php
namespace PhpCachegrindParser\Data;

class Method
{
    private $callees = array();
    private $file;
    private $name;

    function __construct($file, $name)
    {
        $this->$file = $file;
        $this->$name = $name;
    }

    public function addCallee(Method $callee)
    {
    }
}
