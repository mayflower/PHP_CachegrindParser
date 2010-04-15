<?php

require_once 'CachegrindParser/Data/CallTreeNode.php';
use CachegrindParser\Data\CallTreeNode;

require_once 'Test/TestUtils.php';

require_once 'PHPUnit/Framework.php';

class CallTreeNodeTest extends PHPUnit_Framework_TestCase
{

    /**
     * Tests if inclusive costs are calculated correctly.
     */
    public function testInclusiveCosts()
    {
        $n1 = new CallTreeNode('file1', 'func1', toCostArray( 2,  3,  5,  7));
        $n2 = new CallTreeNode('file2', 'func2', toCostArray(11, 14, 17, 19));
        $n3 = new CallTreeNode('file3', 'func3', toCostArray( 1,  3,  7, 29));
        $n4 = new CallTreeNode('file4', 'func4', toCostArray( 2, 97,  2,  3));

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

    /**
     * Tests if nodes are correctly merged into their parent
     * if they don't have any children.
     */
    public function testMergeIntoParent()
    {
        $n1 = new CallTreeNode('file1', 'func1', toCostArray( 2,  3,  5,  7));
        $n2 = new CallTreeNode('file2', 'func2', toCostArray(11, 14, 17, 19));

        $n1->addChild($n2);
        $n2->mergeIntoParent();

        $c = $n1->getCosts();
        $this->assertEquals(13, $c['time']);
        $this->assertEquals(14, $c['mem']);
        $this->assertEquals(22, $c['cycles']);
        $this->assertEquals(19, $c['peakmem']);
    }
}
