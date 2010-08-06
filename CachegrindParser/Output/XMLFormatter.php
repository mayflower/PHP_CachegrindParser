<?php

/**
 * This file contains the class CachegrindParser\Output\XMLFormatter.
 *
 * PHP version 5
 *
 * @author Kevin-Simon Kohlmeyer <simon.kohlmeyer@googlemail.com>
 * @author Thomas Bley <thomas.bley@mayflower.de>
 */

namespace CachegrindParser\Output;

use CachegrindParser\Data;

require_once "CachegrindParser/Output/Formatter.php";
require_once "CachegrindParser/Data/CallTreeNode.php";
use CachegrindParser\Data\CallTreeNode;

/**
 * Creates an hierarchical xml formatting.
 */
class XMLFormatter implements Formatter
{

    /**
     * Implements format($tree) declared in interface Formatter.
     */
    public function format($tree)
    {
        $root = new \SimpleXMLElement('<costList/>');
        // Add the summary
        foreach ($tree->getSummary() as $name => $value) {
            $root[$name] = $value;
        }

        // We don't want the root of the tree here.
        $nodeQueue = $tree->getRoot()->getChildren();

        while ($nodeQueue) {
            $node = array_pop($nodeQueue);
            if ( $node->getFuncname() == 'dropped' )
                continue;

            self::addCall($root, $node);
            foreach ($node->getChildren() as $child) {
                array_push($nodeQueue, $child);
            }
        }

        return $root->asXML();
    }

    /*
     * Adds the call specified by $node to the costList tree starting with $root
     *
     * @param SimpleXMLElement                   $root The root of the xml tree.
     * @param CachegrindParser\Data\CallTreeNode $node The node to add to $root.
     */
    private static function addCall(\SimpleXMLElement $root,
                                     CallTreeNode $node)
    {
        // Four things to do here:
        // 1. Get or create the file element
        $file = $node->getFilename();
        $fileElement = $root->xpath("./file[@name=\"$file\"]");
        if ($fileElement) {
            $fileElement = $fileElement[0];
        } else {
            $fileElement = $root->addChild('file');
            $fileElement['name'] = $file;
        }

        // 2. Get or create the function/method element
        // Note that $funcElement can be either a <method> or a <function> tag.
        $func = $node->getFuncname();
        if (strpos($func, '->')) {
            $funcElement = self::insertMethod($fileElement, $func);
        } else {
            $funcElement = self::insertFunction($fileElement, $func);
        }

        // 3. Add a new Call element
        $callElement = $funcElement->addChild('call');
        $callElement['id'] = md5($node->getPath());
        $callElement['count'] = $node->getCallCount();

        // 4. Add the costs.
        $costsElement = $callElement->addChild('ownCosts');
        foreach ($node->getCosts() as $name => $value) {
            $costsElement[$name] = $value;
        }

        $inclusiveCostsElement = $callElement->addChild('inclusiveCosts');
        foreach ($node->getInclusiveCosts() as $name => $value) {
            $inclusiveCostsElement[$name] = $value;
        }

        // 4. Get or create the called functions elements
        if ($node->getChildren()) {
            $calledFunctionsElement = $callElement->addChild('calledFunctions');
            foreach ($node->getChildren() as $child) {

                if ( $child->getFuncname() == 'dropped' )
                    continue;

                $e = $calledFunctionsElement->addChild('function');
                $e['file'] = $child->getFilename();
                $e['name'] = $child->getFuncname();
                $e['id']   = md5($child->getPath());
                $e['count']   = $child->getCallCount();
            }
        }

    }

    /*
     * Inserts a class and method element into the given parent if they
     * don't already exist.
     *
     * @param  SimpleXMLElement $fileElement The element to insert the <class>
     *                                       and <method element into.
     * @param  string           $funcString   String with ClassName->methodName
     * @return SimpleXMLElement The <method> element.
     */
    private static function insertMethod(\SimpleXMLElement $fileElement,
                                         $funcString)
    {
        $sig = explode('->', $funcString);
        $className = $sig[0];
        $methodName = $sig[1];

        $classElement = $fileElement->xpath("./class[@name=\"{$className}\"]");
        if ($classElement) {
            $classElement = $classElement[0];
        } else {
            $classElement = $fileElement->addChild('class');
            $classElement['name'] = $className;
        }

        $methodElement = $classElement->xpath(
            './method[@name="'.
            $methodName . '"]'
        );
        if ($methodElement) {
            $methodElement = $methodElement[0];
        } else {
            $methodElement = $classElement->addChild('method');
            $methodElement['name'] = $methodName;
        }
        return $methodElement;
    }

    /*
     * Inserts a function Element in the given parent if it doesn't
     * already exist.
     *
     * @param  SimpleXMLElement $parentElement The element to insert the
     *                                         <function> element into.
     * @param  string           $funcString   Function name
     * @return The <function> element.
     */
    private static function insertFunction(\SimpleXMLElement $parentElement,
                                           $funcName)
    {
        $funcElement = $parentElement->xpath(
            './function[@name="'.
            $funcName . '"]'
        );
        if ($funcElement) {
            $funcElement = $funcElement[0];
        } else {
            $funcElement = $parentElement->addChild('function');
            $funcElement['name'] = $funcName;
        }
        return $funcElement;
    }
}