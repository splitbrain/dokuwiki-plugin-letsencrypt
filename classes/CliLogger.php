<?php

namespace dokuwiki\plugin\letsencrypt\classes;

/**
 * Minimal logger that logs directly to command line
 */
class CliLogger {

    protected $cli;

    /**
     * CliLogger constructor.
     *
     * @param \DokuCLI|\splitbrain\phpcli\CLI $cli
     */
    public function __construct($cli) {
        $this->cli = $cli;
    }

    function __call($name, $arguments) {
        if(is_callable(array($this->cli, $name))){
            $this->cli->$name($arguments[0]);
        } else {
            $this->cli->info($arguments[0]);
        }
    }
}
