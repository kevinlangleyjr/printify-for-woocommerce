<?php
class Printify_Api {

    private $agent    = 'Printify WC plugin';
    private $url      = 'https://api-prod.printify.com/v1/';
    private $response = null;

    const VERSION     = '1.1';

    public function __construct()
    {
        $this->agent .= ' ' . self::VERSION . ' (WP '. get_bloginfo( 'version' ) . ' + WC ' . WC()->version.')';

        if ( ! function_exists('json_decode') || ! function_exists('json_encode')) {
            throw new Exception('PHP JSON extension is required for the Printify API library to work!');
        }
    }

    public function get($path, $params = [])
    {
        return $this->request('GET', $path, $params);
    }

    public function post($path, $params = [], $data = [])
    {
        return $this->request('POST', $path, $params, $data);
    }

    private function request($method, $path, array $params = [], $data = null)
    {

        $url = trim($path,'/');

        if ( ! empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $this->response = wp_remote_get($this->url . $url, array(
            'timeout'    => 10,
            'user-agent' => $this->agent,
            'method'     => $method,
            'body'       => $data !== null ? json_encode($data) : null,
            'headers'    => [
                'Content-Type' => 'application/json',
            ]
        ));

        if (is_wp_error($this->response))
        {
            throw new Exception("Printify API request failed - ". $this->response->get_error_message());
        }

        $body = json_decode($this->response['body'], true);
        // print_r($this->response);

        return $body;
    }

}