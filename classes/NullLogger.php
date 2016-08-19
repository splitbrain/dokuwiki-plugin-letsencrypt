<?php

namespace dokuwiki\plugin\letsencrypt\classes;

/**
 * Logger that does absolutely nothing
 */
class NullLogger {
    function __call($name, $arguments) {
    }
}
