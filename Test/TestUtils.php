<?php

function toCostArray($time, $mem, $cycles, $peakmem)
{
    return array(
        'time'    => $time,
        'mem'     => $mem,
        'cycles'  => $cycles,
        'peakmem' => $peakmem,
    );
}
