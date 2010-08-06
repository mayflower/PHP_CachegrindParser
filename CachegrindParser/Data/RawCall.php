<?php

/**
 * This file contains the class CachegrindParser\Data\RawCall.
 *
 * PHP version 5
 *
 * @author Kevin-Simon Kohlmeyer <simon.kohlmeyer@googlemail.com>
 */

namespace CachegrindParser\Data;

/**
 * This class represents one cfn block from a cachegrind log file.
 *
 * It stores the functions name, the number of calls made to it and the
 * costs of the calls.
 */
class RawCall
{
    private $_cfn;

    private $_calls;
    private $_costs;

    /**
     * Creates a new RawCall object with the given values.
     *
     * @param string $funcname The function name.
     * @param array  $callData Array with:
     *                         'calls' => integer Number of calls.
     * @param array  $costs Array with: 'time'    => integer
     *                                  'mem'     => integer
     *                                  'cycles'  => integer
     *                                  'peakmem' => integer
     */
    function __construct($funcname, $callData, $costs)
    {
        $this->_cfn = $funcname;
        $this->_calls = $callData['calls'];

        $this->_costs = $costs;
    }

    /**
     * Returns the name of this function.
     *
     * @return string Function name.
     */
    public function getFuncname()
    {
        return $this->_cfn;
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
     * Returns the number of calls represented by this object.
     *
     * @return integer The number of calls.
     */
    public function getCalls()
    {
        return $this->_calls;
    }
}

