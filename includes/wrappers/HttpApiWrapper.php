<?php

namespace Divido\Woocommerce\FinanceGateway\Wrappers;
use Divido\Woocommerce\FinanceGateway\Exceptions\ResponseException;

/**
 * A Wrapper for the Wordpress HTTP API functions
 * https://developer.wordpress.org/plugins/http-api/
 * 
 */
class HttpApiWrapper{

    const TIMEOUT = '30';
    private string $baseUri;
    private array $defaultArgs = [
        'timeout' => self::TIMEOUT,
        'headers' => []
    ];

    public function __construct(string $baseUri){
        $this->baseUri = $baseUri;
    }

    public function addHeader(string $key, string $value){
        $this->defaultArgs['headers'][$key] = $value;
    }

    public function get(string $path, ?array $params = null):array{

        $args = array_merge($this->defaultArgs, [
            'method' => 'GET'
        ]);

        $url = sprintf("%s%s", $this->baseUri, $path);
        if($params){
            $queryParams = http_build_query($params);
            $url .= sprintf("?%s", $queryParams);
        }

        $response = wp_remote_get(
            $url,
            $args
        );

        $this->validateResponse($response, 'GET', $path);

        return $response;
    }

    public function post(string $path, mixed $body): array{
        $args = array_merge($this->defaultArgs, [
            'method' => 'POST',
            'body' => $body
        ]);

        $url = sprintf("%s%s", $this->baseUri, $path);

        $response = wp_remote_post(
            $url,
            $args
        );
        
        $this->validateResponse($response, 'POST', $path);

        return $response;
    }

    public function patch(string $path, mixed $body):array{
        $args = array_merge($this->defaultArgs, [
            'method' => 'PATCH',
            'body' => $body
        ]);

        $url = sprintf("%s%s?%s", $this->baseUri, $path);

        $response = wp_remote_post(
            $url,
            $args
        );

        $this->validateResponse($response, 'PATCH', $path);

        return $response;
    }

    /**
     * Ensures we're actually receiving an array, as expected from the wp_remote_ functions
     * https://developer.wordpress.org/reference/functions/wp_remote_post/
     *
     * @param array|WP_Error $response
     * @param string $method
     * @param string $path
     * @return void
     */
    private function validateResponse($response, string $method, string $path){
        if(is_object($response) && get_class($response) === 'WP_Error'){
            throw new ResponseException(
                sprintf("Error in response: %s", $response->get_error_message()),
                500,
                $method,
                $path
            );
        }

        if(!is_array($response)){
            throw new ResponseException(
                "Unexpected Response - expected array",
                500,
                $method,
                $path
            );
        }
    }

}