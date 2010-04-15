<?php

require_once 'CachegrindParser/Data/CallTreeNode.php';
require_once 'PHPUnit/Framework.php';

use CachegrindParser\Data\CallTreeNode;

class CallTreeNodeTest extends PHPUnit_Framework_TestCase
{

    /**
     * Tests if inclusive costs are calculated correctly.
     */
    public function testInclusiveCosts() {
        $n1 = new CallTreeNode('file1', 'func1', array(
            'time'    => 2,
            'mem'     => 3,
            'cycles'  => 5,
            'peakmem' => 7,
        ));
        $n2 = new CallTreeNode('file2', 'func2', array(
            'time'    => 11,
            'mem'     => 14,
            'cycles'  => 17,
            'peakmem' => 19,
        ));
        $n3 = new CallTreeNode('file3', 'func3', array(
            'time'    => 1,
            'mem'     => 3,
            'cycles'  => 7,
            'peakmem' => 29,
        ));
        $n4 = new CallTreeNode('file4', 'func4', array(
            'time'    => 2,
            'mem'     => 97,
            'cycles'  => 2,
            'peakmem' => 3,
        ));

        $n1->addChild($n2);
        $c = $n1->getInclusiveCosts();
        $this->assertEquals(13, $c['time']);
        $this->assertEquals(14, $c['mem']);
        $this->assertEquals(22, $c['cycles']);
        $this->assertEquals(19, $c['peakmem']);

        $n2->addChild($n3);
        $n2->addChild($n4);
        $c = $n1->getInclusiveCosts();
        $this->assertEquals(16, $c['time']);
        $this->assertEquals(97, $c['mem']);
        $this->assertEquals(31, $c['cycles']);
        $this->assertEquals(29, $c['peakmem']);
    }
}

