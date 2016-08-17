<?php



namespace dokuwiki\plugin\letsencrypt\classes;

use Analogic\ACME\ClientInterface;

require_once __DIR__ . '/../Lescript.php';


class Lescript extends \Analogic\ACME\Lescript {

    public function __construct($certificatesDir, $webRootDir, $logger) {
        if(!$certificatesDir) throw new Exception('no cert dir');
        if(!$webRootDir) throw new Exception('no root dir');
        $client = new Client($this->ca);

        parent::__construct($certificatesDir, $webRootDir, $logger, $client);
    }

}
