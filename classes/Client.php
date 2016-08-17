<?php

namespace dokuwiki\plugin\letsencrypt\classes;

require_once __DIR__ . '/../Lescript.php';

class Client implements \Analogic\ACME\ClientInterface {

    protected $base;

    protected $http;

    /**
     * Constructor
     *
     * @param string $base the ACME API base all relative requests are sent to
     */
    public function __construct($base) {
        $this->base = $base;
        $this->http = new \DokuHTTPClient();
        $this->http->keep_alive = false; // SSL seems to break after several requests
    }

    /**
     * Send a POST request
     *
     * @param string $url URL to post to
     * @param array $fields fields to sent via post
     * @return array|string the parsed JSON response, raw response on error
     */
    public function post($url, $fields) {
        if(!preg_match('/^https?:\/\//', $url)) $url = $this->base . $url;
        $this->http->headers['Content-Type'] = 'application/json';
        $response = $this->http->post($url, $fields);
        if($response === false) $response = $this->http->resp_body;
        $data = json_decode($response, true);
        return $data === null ? $response : $data;
    }

    /**
     * @param string $url URL to request via get
     * @return array|string the parsed JSON response, raw response on error
     */
    public function get($url) {
        if(!preg_match('/^https?:\/\//', $url)) $url = $this->base . $url;
        $response = $this->http->get($url);
        if($response === false) $response = $this->http->resp_body;
        $data = json_decode($response, true);
        return $data === null ? $response : $data;
    }

    /**
     * Returns the Replay-Nonce header of the last request
     *
     * if no request has been made, yet. A GET on $base/directory is done and the
     * resulting nonce returned
     *
     * @return string
     * @throws Exception
     */
    public function getLastNonce() {
        if(isset($this->http->resp_headers['replay-nonce'])) {
            return $this->http->resp_headers['replay-nonce'];
        } else {
            $result = $this->get('/directory');
            if(!$result) throw new Exception('Failed to get nonce');
            return $this->getLastNonce();
        }

    }

    /**
     * Return the Location header of the last request
     *
     * returns null if last request had no location header
     *
     * @return string|null
     */
    public function getLastLocation() {
        if(isset($this->http->resp_headers['location'])) {
            return $this->http->resp_headers['location'];
        } else {
            return null;
        }
    }

    /**
     * Return the HTTP status code of the last request
     *
     * @return int
     */
    public function getLastCode() {
        return (int) $this->http->status;
    }

    /**
     * Get all Link headers of the last request
     *
     * @return string[]
     */
    public function getLastLinks() {
        if(preg_match('~<(.+)>;rel="up"~', $this->http->resp_headers['link'], $matches)) {
            return array($matches[1]);
        }
        return array();
    }
}
