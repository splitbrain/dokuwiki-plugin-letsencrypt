#!/usr/bin/php
<?php

use splitbrain\phpcli\Options;

if(!defined('DOKU_INC')) define('DOKU_INC', realpath(__DIR__ . '/../../../') . '/');
define('NOSESSION', 1);
require_once(DOKU_INC . 'inc/init.php');

class LetsEncrypt extends DokuWiki_CLI_Plugin {

    /** @var helper_plugin_letsencrypt $helper */
    protected $helper;

    /**
     * LetsEncrypt constructor.
     */
    public function __construct() {
        parent::__construct();
        $this->helper = plugin_load('helper', 'letsencrypt');
    }

    /**
     * Register options and arguments on the given $options object
     *
     * @param DokuCLI_Options $options
     * @return void
     */
    protected function setup(Options $options) {
        $options->setHelp(
            'This is a command line interface to the letsencrypt plugin. It allows renewing ' .
            'certificates from cron jobs etc.' . "\n" .
            'When run without any options, it lists all domains and their certificate status'
        );

        $options->registerOption('update', 'Update the certificates', 'u');
        $options->registerOption('force', 'Force a certificate update even when none is needed', 'f');
        $options->registerOption('run-on-update', 'Run this command when the certificate has been updated', 'r', 'command');
        $options->registerOption('quiet', 'Do not print anything except fatal errors (and whatever the run-on-update program outputs)', 'q');
    }

    /**
     * Your main program
     *
     * Arguments and options have been parsed when this is run
     *
     * @param DokuCLI_Options $options
     * @return void
     */
    protected function main(Options $options) {
        if(!$this->helper->getRoot()) $this->fatal('no webserver root directory set or detected');
        if(!$this->helper->getCertDir()) $this->fatal('no certificate directory set');
        if(!$this->helper->hasAccount()) $this->fatal('no letsencrypt account set up, yet');

        $quiet = $options->getOpt('quiet');

        $domains = $this->helper->getAllDomains();
        if(!$quiet) $this->printDomains($domains);

        if($options->getOpt('update')) {
            if(!$options->getOpt('force') && !$this->updateNeeded($domains)) {
                if(!$quiet) $this->success('No update needed');
                exit(0);
            }

            if(!$quiet) $this->helper->setCliLogger($this);
            $this->helper->updateCerts();
            if($options->getOpt('run-on-update')) {
                passthru($options->getOpt('run-on-update'), $return);
                exit($return);
            }
        }
    }

    /**
     * @param $domains
     * @return bool
     */
    protected function updateNeeded($domains) {
        foreach($domains as $domain => $expire) {
            if($expire < 31) return true;
        }
        return false;
    }

    /**
     * list all the domains
     * @param $domains
     */
    protected function printDomains($domains) {
        foreach($domains as $domain => $expire) {
            if($expire > 30) {
                $this->colors->ptln(sprintf("%-50s" . $this->helper->getLang('valid'), $domain, $expire), 'green');
            } elseif($expire == 0) {
                $this->colors->ptln(sprintf("%-50s" . $this->helper->getLang('invalid'), $domain), 'red');
            } else {
                $this->colors->ptln(sprintf("%-50s" . $this->helper->getLang('valid'), $domain, $expire), 'yellow');
            }
        }
    }
}

$le = new LetsEncrypt();
$le->run();
