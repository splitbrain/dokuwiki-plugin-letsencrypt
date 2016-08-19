<?php
/**
 * DokuWiki Plugin letsencrypt (Admin Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <andi@splitbrain.org>
 */

// must be run within Dokuwiki
use dokuwiki\Form\Form;

if(!defined('DOKU_INC')) die();

class admin_plugin_letsencrypt extends DokuWiki_Admin_Plugin {

    /** @var helper_plugin_letsencrypt $helper */
    protected $helper;

    public function __construct() {
        $this->helper = plugin_load('helper', 'letsencrypt');
    }

    /**
     * @return int sort number in admin menu
     */
    public function getMenuSort() {
        return 555;
    }

    /**
     * @return bool true if only access for superuser, false is for superusers and moderators
     */
    public function forAdminOnly() {
        return false;
    }

    /**
     * Should carry out any processing required by the plugin.
     */
    public function handle() {

    }

    public function execute() {
        global $INPUT;

        try {
            echo '<div class="log">';
            $this->helper->setHTMLLogger();

            if($INPUT->bool('init')) {
                $countries = $this->getCountries();
                $code = $INPUT->str('country');
                $country = $countries[$code];
                $this->helper->register($code, $country, $INPUT->str('email'));
            }
            if($INPUT->bool('sign')) {
                $this->helper->updateCerts();
            }

            echo '</div>';
        } catch(\Exception $e) {
            echo '</div>';
            msg($e->getMessage(), -1, $e->getLine(), $e->getFile());
        }
    }

    /**
     * Render HTML output, e.g. helpful text and a form
     */
    public function html() {
        echo '<div id="plugin__letsencrypt">';
        ptln('<h1>' . $this->getLang('menu') . '</h1>');

        echo '<div class="log_area">';
        $this->execute();
        echo '</div>';

        echo '<dl class="info_area">';
        $dirs = $this->html_directories();
        $acct = $this->html_account();
        $doms = $this->html_domains();
        echo '</dl>';

        echo '<div class="action_area">';
        if($dirs) {
            if($acct) {
                if($doms) {
                    $this->form_domains();
                }
            }else {
                $this->form_account();
            }
        }
        echo '</div>';

        echo '</div>';
    }

    /**
     * Output info on the directories
     *
     * @return bool directories set up?
     */
    protected function html_directories() {
        $ok = true;
        $certdir = $this->helper->getCertDir();
        $rootdir = $this->helper->getRoot();


        echo '<dt>Certificate Directory</dt>';
        if($certdir) {
            echo '<dd><code>' . $certdir . '</code></dd>';
        } else {
            echo '<dd class="error">Not set up!</dd>';
            $ok = false;
        }
        echo '<dt>Webserver Root Directory</dt>';
        if($rootdir) {
            echo '<dd><code>' . $rootdir . '</code></dd>';
        } else {
            echo '<dd class="error">Not set up!</dd>';
            $ok = false;
        }

        return $ok;
    }

    /**
     * Output info about the account
     *
     * @return bool account available?
     */
    protected function html_account() {
        echo '<dt>Let\'s Encrypt Account</dt>';
        if(file_exists($this->helper->getCertDir() . '/_account/private.pem')) {
            echo '<dd>already set up</dd>';
            return true;
        } else {
            echo '<dd class="error">Not set up!</dd>';
            return false;
        }
    }



    /**
     * List the detected domains
     *
     * @return bool found any domains?
     */
    protected function html_domains() {
        $domains = $this->helper->getAllDomains();
        echo '<dt>Domains</dt>';

        if(!$domains) {
            echo '<dd class="error">None found</dd>';
            return false;
        }

        foreach($domains as $domain => $expire) {
            echo '<dd>';
            echo hsc($domain);

            if($expire > 30) {
                echo sprintf(' <span class="valid">' . 'valid for %d days' . '</span>', $expire);
            } elseif($expire == 0) {
                echo ' <span class="valid">' . 'no valid certificate' . '</span>';
            } else {
                echo sprintf(' <span class="renew">' . 'valid for %d days' . '</span>', $expire);
            }
            echo '</dd>';
        }
        return true;
    }

    /**
     * Form to create new LE account
     */
    protected function form_account() {
            $license = 'You agree to the <a href="%s" class="media mediafile mf_pdf">License</a>';
            $license = sprintf($license, 'https://letsencrypt.org/documents/LE-SA-v1.1.1-August-1-2016.pdf');

            $form = new Form();
            $form->addFieldsetOpen('Create new Account');
            $form->addTextInput('email', 'E-Mail Address')->addClass('block');
            $form->addDropdown('country', $this->getCountries(), 'Country')->addClass('block');
            $form->addHTML("<p>$license</p>");
            $form->addButton('init', 'Create Account')->attr('type', 'submit')->val(1);
            echo $form->toHTML();
    }

    /**
     * Form to request Certificates
     */
    protected function form_domains() {
        $form = new Form();
        $form->addFieldsetOpen('Sign Domains');
        $form->addButton('sign', 'Sign Domains')->attr('type', 'submit')->val(1);
        echo $form->toHTML();
    }




    /**
     * @return array
     */
    protected function getCountries() {
        $out = array();
        $raw = file(__DIR__ . '/country-codes.csv');
        foreach($raw as $line) {
            list($country, $code) = explode(',', trim($line));
            $out[$code] = $country;
        }

        return $out;
    }

}

// vim:ts=4:sw=4:et:
