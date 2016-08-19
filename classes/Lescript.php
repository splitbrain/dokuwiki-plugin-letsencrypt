<?php



namespace dokuwiki\plugin\letsencrypt\classes;


require_once __DIR__ . '/../Lescript.php';


class Lescript extends \Analogic\ACME\Lescript {

    public function __construct($certificatesDir, $webRootDir, $logger) {
        if(!$certificatesDir) throw new Exception('no cert dir');
        if(!$webRootDir) throw new Exception('no root dir');
        $client = new Client($this->ca);

        parent::__construct($certificatesDir, $webRootDir, $logger, $client);
    }

    /**
     * All our certificates are for the same setup
     *
     * @param string $domain ignored
     * @return string
     */
    protected function getDomainPath($domain) {
        return parent::getDomainPath('wiki');
    }

    /**
     * Returns info about the given's domain certificate
     *
     * @param string $domain
     * @return array|null
     */
    public function getCertInfo($domain) {
        $certfile = $this->certificatesDir.'/'.$domain.'/cert.pem';
        if (!file_exists($certfile)) {
            return null;
        }

        $data = openssl_x509_parse(file_get_contents($certfile));

        // parse some data and add it for convenience
        $domains = explode(',', $data['extensions']['subjectAltName']);
        $domains = array_map(function($domain){
            return preg_replace('/^DNS:/', '', trim($domain));
        }, $domains);

        $validto = $data['validTo_time_t'];
        $expiredays = floor(($validto - time()) / (60*60*24));
        if($expiredays < 0) $expiredays = 0;
        $renew =  ($expiredays < 31);

        $data['domains'] = $domains;
        $data['expires_in_days'] = $expiredays;
        $data['should_renew'] = $renew;
        return $data;
    }

}
