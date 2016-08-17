<?php

namespace dokuwiki\plugin\letsencrypt\classes;

class HTMLLogger {
    public $logs = array();

    function __call($name, $arguments) {
        echo date('Y-m-d H:i:s') . " [$name] ${arguments[0]}<br />";
        echo str_pad('', 50000, ' ');
    }
}
