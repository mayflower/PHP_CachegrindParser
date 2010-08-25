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
        $node = new CallTreeNode('file1', 'func1', toCostArray(2,  3,  5,  7));
        $nodeChild = new CallTreeNode(
            'file2', 'func2', toCostArray(11, 14, 17, 19)
        );
        $nodeSubChild = new CallTreeNode(
            'file3', 'func3', toCostArray(1,  3,  7, 29)
        );

        $node->addChild($nodeChild);
        $node->addChild($nodeSubChild);
        $costs = $node->getInclusiveCosts();
        $this->assertEquals(14, $costs['time']);
        $this->assertEquals(14, $costs['mem']);
        $this->assertEquals(29, $costs['cycles']);
        $this->assertEquals(29, $costs['peakmem']);
    }

    /**
     * Tests that inclusive costs are still calculated correctly after
     * adding another child.
     */
    public function testInclusiveCostsWithNewChildren()
    {
        $node = new CallTreeNode('file1', 'func1', toCostArray(2,  3,  5,  7));
        $nodeChild = new CallTreeNode(
            'file2', 'func2', toCostArray(11, 14, 17, 19)
        );
        $nodeSubChild = new CallTreeNode(
            'file3', 'func3', toCostArray(1,  3,  7, 29)
        );
        $nodeSubSubChild = new CallTreeNode(
            'file4', 'func4', toCostArray(2, 97,  2,  3)
        );

        $node->addChild($nodeChild);
        $nodeChild->addChild($nodeSubChild);
        $nodeChild->addChild($nodeSubSubChild);
        $costs = $node->getInclusiveCosts();
        $this->assertEquals(16, $costs['time']);
        $this->assertEquals(97, $costs['mem']);
        $this->assertEquals(31, $costs['cycles']);
        $this->assertEquals(29, $costs['peakmem']);
    }

    /**
     * Tests if nodes are correctly merged into the parent's children
     */
    public function testMergeChild()
    {
        $node = new CallTreeNode('file1', 'func1', toCostArray(2,  3,  5,  7));
        $nodeChild = new CallTreeNode(
            'file2', 'func2', toCostArray(11, 14, 17, 19)
        );
        $node->addChild($nodeChild);

        $nodeb = new CallTreeNode(
            'file1', 'func1', toCostArray(22, 23, 25, 27)
        );
        $nodeChildb = new CallTreeNode(
            'file2', 'func2', toCostArray(12, 15, 18, 20)
        );

        $nodeb->addChild($nodeChildb);
        $node->mergeChild($nodeChildb);

        $costs = $nodeChild->getCosts();
        $this->assertEquals(23, $costs['time']);
        $this->assertEquals(15, $costs['mem']);
        $this->assertEquals(35, $costs['cycles']);
        $this->assertEquals(20, $costs['peakmem']);
    }


    /**
     * Tests if nodes are correctly merged into their parent
     * if they don't have any children.
     */
    public function testMergeIntoParent()
    {
        $node = new CallTreeNode('file1', 'func1', toCostArray(2,  3,  5,  7));
        $nodeRef = new CallTreeNode(
            'file2', 'func2', toCostArray(11, 14, 17, 19)
        );

        $node->addChild($nodeRef);
        $nodeRef->mergeIntoParent();

        $costs = $node->getCosts();
        $this->assertEquals(13, $costs['time']);
        $this->assertEquals(14, $costs['mem']);
        $this->assertEquals(22, $costs['cycles']);
        $this->assertEquals(19, $costs['peakmem']);
    }

    /**
     * Tests combineSimilarChildren method.
     */
    public function testSimilarChildren()
    {
        $node = new CallTreeNode(
            'parent', 'pfunc', toCostArray(2,  3,  5,  7)
        );
        $nodeChild = new CallTreeNode(
            'somefile', 'func', toCostArray(11, 14, 17, 19)
        );
        $nodeSubChild = new CallTreeNode(
            'somefile', 'func', toCostArray(1,  3,  7, 29)
        );

        $node->addChild($nodeChild);
        $node->addChild($nodeSubChild);

        $node->combineSimilarChildren();
        $children = $node->getChildren();
        $this->assertEquals(1, count($children));
        $costs = $children[0]->getCosts();

        $this->assertEquals(12, $costs['time']);
        $this->assertEquals(14, $costs['mem']);
        $this->assertEquals(24, $costs['cycles']);
        $this->assertEquals(29, $costs['peakmem']);
    }
}
