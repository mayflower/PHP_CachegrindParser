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

    private $_fl;
    private $_fn;

    private $_costs;

    private $_subcalls = 0;

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
        $this->_fl = $filename;
        $this->_fn = $funcname;

        $this->_costs = $costs;
    }

    /**
     * Add a subcall to this entry.
     *
     * @param int $count Number of subCalls
     */
    public function addCall( $count )
    {
        $this->_subcalls += $count;
    }


    /**
     * Returns the filename of this function.
     *
     * @return string The filename.
     */
    public function getFilename()
    {
        return $this->_fl;
    }

    /**
     * Returns the name of this function.
     *
     * @return string Function name.
     */
    public function getFuncname()
    {
        return $this->_fn;
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
        return $this->_costs;
    }

    /**
     * Returns all calls made by this entry.
     *
     * @return int Int with number of subCalls.
     */
    public function getSubcalls()
    {
        return $this->_subcalls;
    }
}

