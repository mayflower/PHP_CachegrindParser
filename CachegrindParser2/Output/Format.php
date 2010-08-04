<?php
/**
 * This file contains the class CachegrindParser\Output\Format.
 *
 * PHP version 5
 *
 * @author Thomas Bley <thomas.bley@mayflower.de>
 * @version 2.0
 */

/**
 * This class converts input to an output representation
 */
class CachegrindParser2_Output_Format
{
    /**
     * database handle
     * @var object
     */
    private $_db = null;


    /**
     * output filename
     * @var string
     */
    private $_file = '';


    /**
     * output format
     * @var string
     */
    private $_format = '';


    /**
     * Constructor for Output formatting and conversion
     *
     * @param object $db Database handle (PDO)
     * @param string $file Output filename
     * @param string $format Output format
     */
    public function __construct($db, $file, $format)
    {
        $this->_db         = $db;
        $this->_file    = $file;
        $this->_format     = $format;
    }


    /**
     * Format a tree into the desired export format
     */
    public function format()
    {
        switch ($this->_format) {
            case 'png':
            case 'svg':
            case 'jpg':
            case 'gif':
                $this->_formatImage();
                break;
            case 'dot':
                $this->_formatDot();
                break;
            default:
                throw new Exception('Unknown format: ' . $this->_format);
        }
    }


    /**
     * Export a tree from the database to an graphviz image
     */
    private function _formatImage()
    {
        // create dot output
        $this->_formatDot();

        // copy dot file to /tmp
        $dotFile = tempnam('/tmp', 'cachegrind_' ) . '.dot';
        copy($this->_file, $dotFile);

        // convert dot file to image (uses GraphViz package)
        $cmd = "dot -T" . $this->_format . " -o" .
            escapeshellarg($this->_file) . " " .
            escapeshellarg( $dotFile ) . " 2>&1";

        $output = array();
        exec($cmd, $output);
        if ( !empty( $output ) )
            throw new Exception("Failed executing dot:\n" .
                implode("\n", $output));

        // remove temp-file
        @unlink( $dotFile );
    }


    /**
     * Export a tree from the database to the dot language
     */
    private function _formatDot()
    {
        $output = "digraph {\n".
                  "node [shape=box,style=rounded,fontname=arial,fontsize=17];\n".
                  "edge [color=lightgrey];\n";

        $sql = "
            SELECT
                sum(cost_time) as cost_time,
                sum(cost_cycles) as cost_cycles,
                max(cost_memory) as cost_memory,
                max(cost_memory_peak) as cost_memory_peak
            FROM node
            WHERE path = '{main}'
            GROUP BY path
        ";
        $rootCosts = $this->_db->query($sql)->fetch();

        if (empty($rootCosts)) {
            trigger_error('Could not find a "summary:" section in the file.',
                E_USER_WARNING);
        }

        $sql = "
            SELECT path, sum(count) as count, function_name, filename,
                group_concat(DISTINCT request)||' ('||(max(part)+1)||'x)' as request,
                sum(cost_time) as cost_time,
                sum(cost_cycles) as cost_cycles,
                max(cost_memory) as cost_memory,
                max(cost_memory_peak) as cost_memory_peak,

                sum(cost_time_self) as cost_time_self,
                sum(cost_cycles_self) as cost_cycles_self,
                max(cost_memory_self) as cost_memory_self,
                max(cost_memory_peak_self) as cost_memory_peak_self
            FROM node
            GROUP BY path
            ORDER by path
        ";
        $rows = $this->_db->query($sql);
        foreach ($rows as $row) {

            // output edges and nodes
            $penWidth = min(75, max( 1, ceil(($row['cost_time'] / max($rootCosts['cost_time'], 1)) * 30))); // thickness of edge 1-75

            $edgeLabel =  $row['count'] . 'x';
            $edgeLabel .= ' [' . round($row['cost_time']/1000) . ' ms]';

            $parentPath = substr($row['path'], 0, strrpos($row['path'], '##'));

            $output .= '"' . md5($row['path']) . '" [label=' .
                       $this->_formatDotLabel($row, $rootCosts) . '];'."\n";

            if ($parentPath != '') { // not root node
                $output .= '"'.md5($parentPath).'" -> "'.md5($row['path']).'"';

                $output .= ' [label="'.$edgeLabel.'",penwidth='.
                           $penWidth.'];'."\n";
            }
        }
        $output .= '}';
        file_put_contents($this->_file, $output, LOCK_EX);
    }


    /**
     * Format a dot label (including costs, filename, function, etc)
     *
     * @param array $row Array(Keys: function_name, filename, cost_cycles, cost_memory, cost_memory_peak, cost_time)
     * @param array $rootCosts Total costs of the request Array(Keys: cost_cycles, cost_memory, cost_memory_peak, cost_time)
     * @return string Dot language code
     */
    private function _formatDotLabel($row, $rootCosts)
    {
        $nodeName = $row['function_name'];
        $nodeFile = $row['filename'];

        if ($nodeFile == '') // root node
            $nodeFile = $row['request'];

        $limit = 40;

        // Format nodeName #{60%}...#{limit - 60% - 3}
        if ( strlen( $nodeName ) > $limit ) {
            $first_length = round($limit * 0.6);
            $second_length = $limit - $first_length - 3;
            $nodeName = substr( $nodeName, 0, $first_length ) . '...' .
                        substr( $nodeName, -$second_length );
        }

        // Format nodeFile ...#{limit - 3}
        if ( strlen( $nodeFile ) > $limit )
            $nodeFile = '...' . substr( $nodeFile, ($limit - 3) * (-1) );

        $output  = "<<table border='0'>\n";
        $output .= "<tr><td border='0' align='center' bgcolor='#ED7404'>";
        $output .= "<font color='white'> " . htmlentities( $nodeFile ) .
                   " <br/>" . htmlentities( $nodeName ) . "</font></td></tr>";

        $output .= '<tr><td><table border="0">';
        $output .= '<tr><td align="right">Incl. Costs</td><td></td>';
        $output .= '<td align="right">Own Costs</td></tr>'."\n";

        foreach ( array('cost_cycles', 'cost_memory', 'cost_memory_peak', 'cost_time') as $key ) {
            $rating = 0;
            $keySelf = $key . '_self';

            $part = $row[$keySelf] / max($rootCosts[$key], 1);
            if ($part >= 0.05)
                $rating = 1;
            else
                $rating = 20.0 * ($keySelf / max($rootCosts[$key], 1));

            $bgColor = 'red';
            if ($rating < 0.8)
                $bgColor = 'white';
            elseif ($rating < 0.9)
                $bgColor = 'yellow';

            $output .= "<tr>";
            $output .= "<td align='right' bgcolor='{$bgColor}'>{$row[$key]}</td>\n";
            $output .= "<td align='center' bgcolor='{$bgColor}'> &nbsp;{$key}&nbsp; </td>\n";
            $output .= "<td align='right' bgcolor='{$bgColor}'>{$row[$keySelf]}</td>\n";
            $output .= "</tr>\n";
        }
        $output .= '</table></td></tr>';
        $output .= '</table>>';
        return $output;
    }
}