<?php
namespace PhpCachegrindParser\Data;

/**
 * Represents one or multiple calls to a function.
 *
 * It stores file and function name, the number of calls and the minimum,
 * maximum and total time, memory, cycles and peakmemory.
 */
class AggregatedCalls
{
    private $filename;
    private $funcname;

    private $numCalls;
    private $minValues;
    private $maxValues;
    private $totalValues;

    function __construct($filename, $funcname) {
        $this->filename = $filename;
        $this->funcname = $funcname;
    }

    /**
     * Adds another call to this function. 
     *
     * @param $time         The realtime the call took.
     * @param $memory       Memory usage at the middle of the call.
     * @param $cycles       Cycles used by the call.
     * @param $peakmemory   Peak memory used by the call.
     */
    public function addCall($time, $memory, $cycles, $peakmemory) {
        $this->numCalls += 1;
        $this->updateValues('time', $time);
        $this->updateValues('memory', $memory);
        $this->updateValues('cycles', $cycles);
        $this->updateValues('peakmemory', $peakmemory);
    }

    /*
     * Updates min, max and total values.
     */
    private function updateValues($name, $val) {
        $this->minValues[$name] = min($this->minValues[$name], $val);
        $this->maxValues[$name] = max($this->maxValues[$name], $val);
        $this->totalValues[$name] += $val;
    }
}
