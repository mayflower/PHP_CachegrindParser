<?php

/**
 * This file contains the class CachegrindParser\Data\RawEntry.
 *
 * PHP version 5
 *
 * @author Kevin-Simon Kohlmeyer <simon.kohlmeyer@googlemail.com>
 */

namespace CachegrindParser\Data;

/**
 * Represents a function block in the input file.
 */
class RawEntry
{

    private $fl;
    private $fn;

    private $costs;

    private $subcalls = 0;

    /**
     * Creates a new RawEntry object with the given values.
     *
     * @param string $filename The filename.
     * @param string $funcname The function name.
     * @param array  $costs Array with: 'time'    => integer
     *                                  'mem'     => integer
     *                                  'cycles'  => integer
     *                                  'peakmem' => integer
     */
    function __construct($filename, $funcname, $costs)
    {
        $this->fl = $filename;
        $this->fn = $funcname;

        $this->costs = $costs;
    }

    /**
     * Add a subcall to this entry.
     * 
     * @param int $count Number of subCalls
     */
    public function addCall( $count )
    {
       	$this->subcalls += $count;
    }

    
    /**
     * Returns the filename of this function.
     *
     * @return string The filename.
     */
    public function getFilename()
    {
        return $this->fl;
    }

    /**
     * Returns the name of this function.
     *
     * @return string Function name.
     */
    public function getFuncname()
    {
        return $this->fn;
    }

    /**
     * Returns the costs of this entry.
     *
     * @return array  $costs Array with: 'time'    => integer
     *                                   'mem'     => integer
     *                                   'cycles'  => integer
     *                                   'peakmem' => integer
     */
    public function getCosts()
    {
        return $this->costs;
    }

    /**
     * Returns all calls made by this entry.
     *
     * @return int Int with number of subCalls.
     */
    public function getSubcalls()
    {
        return $this->subcalls;
    }
}

