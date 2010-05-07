<?php

/**
 * This file tests the class CachegrindParser\Data\CallTreeNode.
 *
 * PHP version 5
 *
 * @author Kevin-Simon Kohlmeyer <simon.kohlmeyer@googlemail.com>
 * @author Thomas Bley <thomas.bley@mayflower.de>
 */

use CachegrindParser\Data;
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

        $n1->addChild($n2);
        $n1->addChild($n3);
        $c = $n1->getInclusiveCosts();
        $this->assertEquals(14, $c['time']);
        $this->assertEquals(14, $c['mem']);
        $this->assertEquals(29, $c['cycles']);
        $this->assertEquals(29, $c['peakmem']);
    }

    /**
     * Tests that inclusive costs are still calculated correctly after
     * adding another child.
     */
    public function testInclusiveCostsWithNewChildren()
    {
        $n1 = new CallTreeNode('file1', 'func1', toCostArray( 2,  3,  5,  7));
        $n2 = new CallTreeNode('file2', 'func2', toCostArray(11, 14, 17, 19));
        $n3 = new CallTreeNode('file3', 'func3', toCostArray( 1,  3,  7, 29));
        $n4 = new CallTreeNode('file4', 'func4', toCostArray( 2, 97,  2,  3));

        $n1->addChild($n2);
        $c = $n1->getInclusiveCosts();
        $n2->addChild($n3);
        $n2->addChild($n4);
        $c = $n1->getInclusiveCosts();
        $this->assertEquals(16, $c['time']);
        $this->assertEquals(97, $c['mem']);
        $this->assertEquals(31, $c['cycles']);
        $this->assertEquals(29, $c['peakmem']);
    }
    
    /**
     * Tests if nodes are correctly merged into the parent's children
     */
    public function testMergeChild()
    {
        $n1 = new CallTreeNode('file1', 'func1', toCostArray( 2,  3,  5,  7));
        $n2 = new CallTreeNode('file2', 'func2', toCostArray(11, 14, 17, 19));
        $n1->addChild($n2);
        
        $n1b = new CallTreeNode('file1', 'func1', toCostArray( 22,  23,  25,  27));
        $n2b = new CallTreeNode('file2', 'func2', toCostArray(12, 15, 18, 20));
        $n1b->addChild($n2b);
        
        $n1->mergeChild($n2b);

        $c = $n2->getCosts();
        $this->assertEquals(23, $c['time']);
        $this->assertEquals(15, $c['mem']);
        $this->assertEquals(35, $c['cycles']);
        $this->assertEquals(20, $c['peakmem']);
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

    /**
     * Tests combineSimilarChildren method.
     */
    public function testSimilarChildren()
    {
        $n1 = new CallTreeNode('parent', 'pfunc', toCostArray( 2,  3,  5,  7));
        $n2 = new CallTreeNode('somefile', 'func', toCostArray(11, 14, 17, 19));
        $n3 = new CallTreeNode('somefile', 'func', toCostArray( 1,  3,  7, 29));

        $n1->addChild($n2);
        $n1->addChild($n3);

        $n1->combineSimilarChildren();
        $children = $n1->getChildren();
        $this->assertEquals(1, count($children));
        $c = $children[0]->getCosts();

        $this->assertEquals(12, $c['time']);
        $this->assertEquals(14, $c['mem']);
        $this->assertEquals(24, $c['cycles']);
        $this->assertEquals(29, $c['peakmem']);
    }
}
