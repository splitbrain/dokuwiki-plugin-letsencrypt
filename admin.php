<?php
/**
 * DokuWiki Plugin letsencrypt (Admin Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <andi@splitbrain.org>
 */

// must be run within Dokuwiki
use dokuwiki\Form\Form;
use dokuwiki\plugin\letsencrypt\classes\Lescript;

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
                $this->helper->register($code, $country);
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
        ptln('<h1>' . $this->getLang('menu') . '</h1>');

        $this->execute();



        // check for account
        if(!file_exists($this->helper->getCertDir() . '/_account/private.pem')) {
            echo 'Account does not exist, yet';

            $license = 'You agree to the <a href="%s" class="media mediafile mf_pdf">License</a>';
            $license = sprintf($license, 'https://letsencrypt.org/documents/LE-SA-v1.1.1-August-1-2016.pdf');

            $form = new Form();
            $form->addFieldsetOpen('Create new Account');
            $form->addDropdown('country', $this->getCountries(), 'Country')->addClass('block');
            $form->addHTML("<p>$license</p>");
            $form->addButton('init', 'Create Account')->attr('type', 'submit')->val(1);
            echo $form->toHTML();
        } else {
            echo 'Account key exists';

            $this->html_domains();
        }

    }

    protected function html_domains() {
        echo '<h2>Domains to register</h2>';
        $domains = $this->helper->getAllDomains();

        $html = '<ul>';
        foreach($domains as $domain) {
            $html .= '<li><div class="li">' . hsc($domain) . '</div></li>';
        }
        $html .= '</ul>';

        $form = new Form();
        $form->addFieldsetOpen('Sign Domains');
        $form->addHTML($html);
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
