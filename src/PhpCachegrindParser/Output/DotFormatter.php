<?php
namespace PHPCachegrindParser\Output;

class DotFormatter implements Formatter {
    public function format($parser) {
        $parser->getCallTree();
        return "blub";
    }
}
