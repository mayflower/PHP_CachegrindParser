<?php

/**
 * This file tests the class CachegrindParser\Input\Parser.
 *
 * PHP version 5
 *
 * @author Kevin-Simon Kohlmeyer <simon.kohlmeyer@googlemail.com>
 * @author Thomas Bley <thomas.bley@mayflower.de>
 */

require_once 'CachegrindParser/Input/Parser.php';
use CachegrindParser\Input\Parser;
require_once 'CachegrindParser/Data/CallTreeNode.php';
use CachegrindParser\Data\CallTreeNode;
require_once 'CachegrindParser/Data/CallTree.php';
use CachegrindParser\Data\CallTree;

require_once 'Test/TestUtils.php';

require_once 'PHPUnit/Framework.php';

class ParserTest extends PHPUnit_Framework_Testcase
{
    const testinput = '
version: 0.9.6
cmd: /bin/blub/test
part: 1

events: Time Memory Cycles Peakmemory

fl=php:internal
fn=php::spl_autoload_register
457 7 336 0 10

fl=SomeClass.php
fn=SomeClass->__construct
93 45 472 0 0
cfn=php::spl_autoload_register
calls=1 0 0
457 7 336 0 0

fl=SomeClass.php
fn=SomeClass::getInstance
398 76 1088 0 0
cfn=SomeClass->__construct
calls=1 0 0
93 52 808 0 0

fl=index.php
fn={main}

summary: 159 1088 0 10

0 21 30 0 70
cfn=SomeClass::getInstance
calls=1 0 0
398 76 1088 0 0

fl=php:internal
fn=php::fclose
116 10 500 0 0

';

    public function testGetCallTree() {
    	$parser = new Parser(self::testinput);

        // build what we expect.
        // root
        $root = new CallTreeNode('', '{root}', toCostArray(0, 0, 0, 0));

        // root
        // |-> php::fclose
        $root->addChild(new CallTreeNode('php:internal', 'php::fclose',
                                         toCostArray(10, 500, 0, 0)));

        // root
        // |-> php::fclose
        // |-> {main}
        $main = new CallTreeNode('index.php', '{main}',
                                 toCostArray(21, 30, 0, 70));
        $root->addChild($main);

        // root
        // |-> php::fclose
        // |-> {main}
        //     |-> SomeClass::getInstance
        $getInstance = new CallTreeNode('SomeClass.php',
                                        'SomeClass::getInstance',
                                        toCostArray(76, 1088, 0, 0));
        $main->addChild($getInstance);

        // root
        // |-> php::fclose
        // |-> {main}
        //     |-> SomeClass::getInstance
        //         |-> SomeClass->__construct
        $construct = new CallTreeNode('SomeClass.php',
                                      'SomeClass->__construct',
                                      toCostArray(45, 472, 0, 0));
        $getInstance->addChild($construct);

        // root
        // |-> php::fclose
        // |-> {main}
        //     |-> SomeClass::getInstance
        //         |-> SomeClass->__construct
        //             |-> php::spl_autoload_register
        $sar = new CallTreeNode('php:internal',
                                'php::spl_autoload_register',
                                toCostArray(7, 336, 0, 10));
        $construct->addChild($sar);

        $expected = new CallTree($root, toCostArray(159, 1088, 0, 10));

        $this->assertEquals($expected, $parser->getCallTree());

        //print_r($parser->getCallTree());
    }
}
