<?php

/**
 * This file contains the class CachegrindParser\Output\XMLFormatter.
 *
 * PHP version 5
 *
 * @author Kevin-Simon Kohlmeyer <simon.kohlmeyer@googlemail.com>
 */

namespace CachegrindParser\Output;

require_once "CachegrindParser/Output/Formatter.php";
require_once "CachegrindParser/Data/RawEntry.php";
use \CachegrindParser\Data as Data;

/**
 * Creates an hierarchical xml formatting.
 */
class XMLFormatter implements Formatter
{

    /**
     * Implements format($parser) declared in interface Formatter.
     */
    public function format($parser)
    {
        $entrylist = $parser->getEntryList();

        $root = new \SimpleXMLElement('<costList/>');
        foreach ($entrylist as $entry) {
            self::addEntry($root, $entry);
        }
        return $root->asXML();
    }

    private static function addEntry(\SimpleXMLElement $root,
                                     Data\RawEntry $entry)
    {
        // Four things to do here:
        // 1. Get or create the file element
        // 2. Get or create the function/method element
        // 3. Add the costs.
        // 4. Get or create the subcall elements including costs.

        // But we don't want these
        if (strcmp($entry->getFilename(), 'php:internal') == 0 ||
                strncmp($entry->getFuncname(), 'include::', 9) == 0 || 
                strncmp($entry->getFuncname(), 'require::', 9) == 0 || 
                strncmp($entry->getFuncname(), 'include_once::', 14) == 0 || 
                strncmp($entry->getFuncname(), 'require_once::', 14) == 0) {
            return;
        }

        // 1. Get or create the file element
        $file = $entry->getFilename();
        $fileElement = $root->xpath("./file[@name=\"$file\"]");
        if ($fileElement) {
            $fileElement = $fileElement[0];
        } else {
            $fileElement = $root->addChild('file');
            $fileElement['name'] = $file;
        }
        
        // 2. Get or create the function/method element
        // Note that $funcElement can be either a <method> or a <function> tag.
        $func = $entry->getFuncname();
        if (strpos($func, '->')) {
            $funcElement = self::insertMethod($fileElement, $func);
        } else {
            $funcElement = self::insertFunction($fileElement, $func);
        }

        // 3. Add the costs.
        self::insertCosts($funcElement, $entry->getCosts());

        // 4. Get or create the subcall elements
        $calledFunctionsElement = $funcElement->calledFunctions;
        if (!$calledFunctionsElement && $entry->getSubcalls()) {
            $calledFunctionsElement = $funcElement->addChild('calledFunctions');
        }

        foreach($entry->getSubcalls() as $call) {
            $subcallElement = self::insertFunction($calledFunctionsElement,
                                                   $call->getFuncname());
            $subcallElement['calls'] += $call->getCalls();
            // Add the costs.
            self::insertCosts($subcallElement, $call->getCosts());
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

        $methodElement = $classElement->xpath('./method[@name="'
                                              . $methodName . '"]');
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
     * @param  string           $funcString   String with ClassName->methodName
     * @return The <function> element.
     */
    private static function insertFunction(\SimpleXMLElement $parentElement,
                                           $funcName)
    {
        $funcElement = $parentElement->xpath('./function[@name="'
                                             . $funcName . '"]');
        if ($funcElement) {
            $funcElement = $funcElement[0];
        } else {
            $funcElement = $parentElement->addChild('function');
            $funcElement['name'] = $funcName;
        }
        return $funcElement;
    }

    /*
     * Inserts the costs into the given element.
     */
    private static function insertCosts(\SimpleXMLElement $element, $costs)
    {
        $costsElement = $element->costs;
        foreach ($costs as $name => $value) {
            $costsElement[$name] += $value;
        }
    }
}
