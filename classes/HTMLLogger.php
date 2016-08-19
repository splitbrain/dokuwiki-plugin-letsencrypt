<?php

namespace dokuwiki\plugin\letsencrypt\classes;

/**
 * Minimal logger that writes directly to HTML and flushes the buffers
 */
class HTMLLogger {
    function __call($name, $arguments) {
        $name = substr($name, 0, 1);
        $msg = hsc($arguments[0]);
        echo "[$name] " . date('H:i:s') . " $msg<br />";
        echo str_pad('', 50000, ' ');
        tpl_flush();
    }
}
