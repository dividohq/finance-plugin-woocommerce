<?php

namespace Divido\Woocommerce\FinanceGateway\Proxies;

use Divido\MerchantSDK\Exceptions\MerchantApiBadResponseException;
use Divido\MerchantSDK\Models\Application;
use Divido\MerchantSDK\Models\ApplicationActivation;
use Divido\MerchantSDK\Models\ApplicationCancellation;
use Divido\MerchantSDK\Models\ApplicationRefund;
use Divido\Woocommerce\FinanceGateway\Exceptions\ResponseException;
use Divido\Woocommerce\FinanceGateway\Wrappers\HttpApiWrapper;

/**
 * A proxy between the Merchant API Pub and the HttpApiWrapper
 */
class MerchantApiPubProxy{

    const PATHS = [
        'GET' => [
            'HEALTH' => '/health',
            'PLANS' => '/finance-plans',
            'ENVIRONMENT' => '/environment'
        ],
        'POST' => [
            'APPLICATION' => '/applications',
            'ACTIVATION' => '/applications/%s/activations',
            'REFUND' => '/application/%s/refunds',
            'CANCELLATION' => '/applications/%s/cancellations'
        ],
        'PATCH' => [
            'APPLICATION' => '/application/%s'
        ]
    ];

    const EXPECTED_RESPONSE_CODES = [
        'GET' => [
            'HEALTH' => 200,
            'PLANS' => 200,
            'ENVIRONMENT' => 200
        ],
        'POST' => [
            'APPLICATION' => 201,
            'ACTIVATION' => 201,
            'REFUND' => 201,
            'CANCELLATION' => 201
        ],
        'PATCH' => [
            'APPLICATION' => 200
        ]
    ];

    const HEADER_KEYS = [
        'API_KEY' => 'X-DIVIDO-API-KEY',
        'SHARED_SECRET' => 'X-Divido-Hmac-Sha256'
    ];

    private HttpApiWrapper $wrapper;

    public function __construct(string $baseUri, string $apiKey){
        $this->wrapper = new HttpApiWrapper($baseUri);
        $this->wrapper->addHeader('Accept', 'application/json');
        $this->wrapper->addHeader('Content-Type', 'application/json');
        $this->wrapper->addHeader(self::HEADER_KEYS['API_KEY'], $apiKey);
    }

    /**
     * Adds the shared secret header to our HTTP request
     *
     * @param string $secret
     * @return void
     */
    public function addSecretHeader(string $secret){
        $this->wrapper->addHeader(self::HEADER_KEYS['SHARED_SECRET'], $secret);
    }

    /**
     * Makes a request to the Merchant API Pub health endpoint and returns true if API is healthy
     *
     * @return boolean
     */
    public function getHealth():bool{
        $method = 'GET';
        $action = 'HEALTH';

        $response = $this->wrapper->get(self::PATHS[$method][$action]);
        
        return (
            wp_remote_retrieve_body($response) == 'OK' && 
            wp_remote_retrieve_response_code($response) === 200
        );
    }

    /**
     * Makes a request to the environment endpoint, and returns a json
     * object of the response body
     *
     * @return object
     */
    public function getEnvironment():object{
        $method = 'GET';
        $action = 'ENVIRONMENT';

        $response = $this->wrapper->get(self::PATHS[$method][$action]);
        
        $this->validateResponse($method, $action, $response);
        
        return $this->responseToObj($response);
    }

    /**
     * Makes a request to the finance plans endpoint, and returns a json
     * object of the response body
     *
     * @return object
     */
    public function getFinancePlans() :object{
        $method = 'GET';
        $action = 'PLANS';

        $response = $this->wrapper->get(self::PATHS[$method][$action]);
        
        $this->validateResponse($method, $action, $response);
        
        return $this->responseToObj($response);
    }

    /**
     * Makes a request to create an application, and returns a json
     * object of the response body
     *
     * @param Application $application
     * @return object
     */
    public function postApplication(Application $application): object{
        $method = 'POST';
        $action = 'APPLICATION';

        $body = $application->getJsonPayload();
        
        $response = $this->wrapper->post(self::PATHS[$method][$action], $body);
        
        $this->validateResponse($method, $action, $response);

        return $this->responseToObj($response);
    }

    /**
     * Makes a request to create an activation, and returns a json
     * object of the response body
     *
     * @param string $applicationId
     * @param ApplicationActivation $activation
     * @return object
     */
    public function postActivation(string $applicationId, ApplicationActivation $activation): object{
        $method = 'POST';
        $action = 'ACTIVATION';

        $body = $activation->getJsonPayload();

        $path = sprintf(
            self::PATHS[$method][$action],
            $applicationId
        );

        $response = $this->wrapper->post($path, $body);

        $this->validateResponse($method, $action, $response);

        return $this->responseToObj($response);
    }

    /**
     * Makes a request to create a cancellation, and returns a json
     * object of the response body
     *
     * @param string $applicationId
     * @param ApplicationCancellation $cancellation
     * @return object
     */
    public function postCancellation(string $applicationId, ApplicationCancellation $cancellation): object{
        $method = 'POST';
        $action = 'CANCELLATION';

        $path = sprintf(
            self::PATHS[$method][$action],
            $applicationId
        );

        $body = $cancellation->getJsonPayload();
        $response = $this->wrapper->post($path, $body);

        $this->validateResponse($method, $action, $response);

        return $this->responseToObj($response);
    }

    /**
     * Makes a request to create a refund, and returns a json
     * object of the response body
     *
     * @param string $applicationId
     * @param ApplicationRefund $refund
     * @return object
     */
    public function postRefund(string $applicationId, ApplicationRefund $refund): object{
        $method = 'POST';
        $action = 'REFUND';

        $path = sprintf(
            self::PATHS[$method][$action],
            $applicationId
        );

        $body = $refund->getJsonPayload();
        $response = $this->wrapper->post($path, $body);

        $this->validateResponse($method, $action, $response);

        return $this->responseToObj($response);
    }

    
    /**
     * Makes a request to update an application, and returns a json
     * object of the response body
     *
     * @param Application $application
     * @return object
     */
    public function updateApplication(Application $application): object{
        $method = 'PATCH';
        $action = 'APPLICATION';

        $body = $application->getPayload();

        $path = sprintf(
            self::PATHS[$method][$action],
            $application->getId()
        );

        $response = $this->wrapper->patch($path, $body);

        $this->validateResponse($method, $action, $response);

        return $this->responseToObj($response);
    }

    /**
     * Checks the response is as expected
     *
     * @param string $method
     * @param string $action
     * @param array $response
     * @return void
     */
    private function validateResponse(string $method, string $action, array $response){
        $body = $this->responseToObj($response);
        if(isset($body->error) && $body->error === true && isset($body->code)){
            throw new MerchantApiBadResponseException(
                $body->message ?? "An error occured",
                $body->code,
                [
                    'method' => $method,
                    'action' => $action
                ]
            );
        }
        $statusCode = wp_remote_retrieve_response_code($response);
        if($statusCode !== self::EXPECTED_RESPONSE_CODES[$method][$action]){
            throw new ResponseException(
                "An unexpected error occurred when contacting the Merchant API Pub", $statusCode, $action, $method
            );
        }
    }

    /**
     * Attempts to turn the body of the response into a json object
     *
     * @param array $response
     * @return object
     */
    private function responseToObj(array $response):object{
        return json_decode(
            wp_remote_retrieve_body($response), 
            false, 
            512, 
            JSON_THROW_ON_ERROR
        );
    }
}
