<?php
/**
 * DokuWiki Plugin letsencrypt (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <andi@splitbrain.org>
 */

// must be run within Dokuwiki
use dokuwiki\plugin\letsencrypt\classes\CliLogger;
use dokuwiki\plugin\letsencrypt\classes\HTMLLogger;
use dokuwiki\plugin\letsencrypt\classes\Lescript;
use dokuwiki\plugin\letsencrypt\classes\NullLogger;

if(!defined('DOKU_INC')) die();

require_once __DIR__ . '/Lescript.php';

class helper_plugin_letsencrypt extends DokuWiki_Plugin {

    protected $logger;

    /**
     * helper_plugin_letsencrypt constructor.
     */
    public function __construct() {
        $this->logger = new NullLogger();
    }

    /**
     * switch to HTML logging
     */
    public function setHTMLLogger() {
        $this->logger = new HTMLLogger();
    }

    /**
     * switch to Console logging
     * @param DokuCLI $cli
     */
    public function setCliLogger($cli) {
        $this->logger = new CliLogger($cli);
    }

    /**
     * Get all know domains of this wiki (includes all animals)
     *
     * @return array
     */
    protected function getOwnDomains() {
        $domains = array();

        // get all domains from farming
        /** @var helper_plugin_farmer $farmer */
        $farmer = plugin_load('helper', 'farmer');
        if($farmer) {
            foreach($farmer->getAllAnimals() as $animal) {
                $url = $farmer->getAnimalURL($animal);
                $domains[] = parse_url($url, PHP_URL_HOST);
            }
            $farmconf = $farmer->getConfig();
            if(isset($farmconf['base']['farmhost'])) {
                $domains[] = $farmconf['base']['farmhost'];
            }
        }
        $domains[] = parse_url(DOKU_URL, PHP_URL_HOST);
        return $domains;
    }

    /**
     * Get all domains configured in config file
     *
     * @return array $domains, $excludes
     */
    protected function getDomainConfig() {
        $file = DOKU_CONF . '/letsencrypt-domains.conf';
        if(!file_exists($file)) return array(array(), array());

        $data = file($file);
        $domains = array();
        $excludes = array();

        foreach($data as $line) {
            $line = preg_replace('/(?<![&\\\\])#.*$/', '', $line);
            $line = str_replace('\\#', '#', $line);
            $line = trim($line);
            if(empty($line)) continue;
            if($line[0] == '!') {
                $excludes[] = substr($line, 1);
            } else {
                $domains[] = $line;
            }
        }
        return array($domains, $excludes);
    }

    /**
     * Get all domains
     *
     * @return array
     */
    public function getAllDomains() {
        list($domains, $excludes) = $this->getDomainConfig();
        $domains = array_merge($this->getOwnDomains(), $domains);
        $domains = array_unique($domains);
        $domains = array_filter($domains, array($this, 'domainFilter'));
        $excludes = array_unique($excludes);
        $excludes = array_filter($excludes, array($this, 'domainFilter'));
        $domains = array_diff($domains, $excludes);

        $info = $this->getCertInfo();
        $result = array();
        foreach($domains as $domain) {
            if($info['expires_in_days'] && in_array($domain, $info['domains'])) {
                $expire = $info['expires_in_days'];
            } else {
                $expire = 0;
            }

            $result[$domain] = $expire;
        }

        return $result;
    }

    /**
     * Filter callback to remove non-valid domains
     *
     * @param string $domain
     * @return bool
     */
    public function domainFilter($domain) {
        if(empty($domain)) return false;
        if(strpos($domain, '.') === false) return false; // need at least one dot
        if($domain == 'localhost.localdomain') return false;
        if(preg_match('/^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/', $domain)) return false;
        return true;
    }

    /**
     * @return bool is the account set up already?
     */
    public function hasAccount() {
        return file_exists($this->getCertDir() . '/_account/private.pem');
    }

    public function checkDir() {
        $root = $this->getRoot();
        if($root) {
            $wellknown = "$root/.well-known/acme-challenge";
            io_makeFileDir("$wellknown/test.txt");
            $ok = io_saveFile("$wellknown/test.txt", 'works');
        } else {
            $ok = false;
        }

        $certdir = $this->getConf('certificatedir');
        io_makeFileDir("$certdir/test.txt");
        $ok &= io_saveFile("$certdir/test.txt", 'works');

        return $ok;
    }

    /**
     * Register a new LE account
     *
     * @param string $code
     * @param string $country
     * @param string $email
     */
    public function register($code, $country, $email) {
        set_time_limit(0);
        $lescript = new Lescript($this->getCertDir(), $this->getRoot(), $this->logger);
        $lescript->countryCode = $code;
        $lescript->state = $country;
        $email = trim($email);
        if($email) {
            $lescript->contact = array('mailto:' . $email);
        }

        $lescript->initAccount();
    }

    /**
     * Creates or updates the certificates
     */
    public function updateCerts() {
        set_time_limit(0);
        $lescript = new Lescript($this->getCertDir(), $this->getRoot(), $this->logger);
        $lescript->signDomains(array_keys($this->getAllDomains()));
    }

    /**
     * The directory to store certificates
     *
     * @return string|null null if none is set or can be found
     */
    public function getCertDir() {
        $certdir = $this->getConf('certificatedir');
        if($certdir) return $certdir;

        $root = $this->getRoot();
        if($root) $certdir = fullpath("$root/../certs");
        if($certdir) return $certdir;

        return null;
    }

    /**
     * Get info on our certificate
     *
     * @return array|null
     */
    protected function getCertInfo() {
        $lescript = new Lescript($this->getCertDir(), $this->getRoot(), $this->logger);
        $data = $lescript->getCertInfo('wiki');
        return $data;
    }

    /**
     * The webserver root directory
     *
     * @return string|null null if none is set or detected
     */
    public function getRoot() {
        // did the user tell us?
        $root = $this->getConf('documentroot');
        if($root) return $root;

        // does the webserver tell us?
        $root = $_SERVER['DOCUMENT_ROOT'];
        if($root) return $root;

        // can we figure it out?
        $len = -1 * strlen(DOKU_BASE);
        if(substr(DOKU_INC, $len) == DOKU_BASE) {
            return substr(DOKU_INC, 0, $len);
        }

        // we're lost
        return null;
    }
}

// vim:ts=4:sw=4:et:
