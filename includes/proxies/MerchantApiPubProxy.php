<?php

namespace Divido\Woocommerce\FinanceGateway\Proxies;

use Divido\MerchantSDK\Exceptions\MerchantApiBadResponseException;
use Divido\MerchantSDK\Models\Application;
use Divido\MerchantSDK\Models\ApplicationActivation;
use Divido\MerchantSDK\Models\ApplicationCancellation;
use Divido\MerchantSDK\Models\ApplicationRefund;
use Divido\Woocommerce\FinanceGateway\Exceptions\ResponseException;
use Divido\Woocommerce\FinanceGateway\Wrappers\HttpApiWrapper;

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

    public function addSecretHeader(string $secret){
        $this->wrapper->addHeader(self::HEADER_KEYS['SHARED_SECRET'], $secret);
    }

    public function getHealth():bool{
        $method = 'GET';
        $action = 'HEALTH';

        $response = $this->wrapper->get(self::PATHS[$method][$action]);
        
        return (
            wp_remote_retrieve_body($response) == 'OK' && 
            wp_remote_retrieve_response_code($response) === 200
        );
    }


    public function getEnvironment():object{
        $method = 'GET';
        $action = 'ENVIRONMENT';

        $response = $this->wrapper->get(self::PATHS[$method][$action]);
        
        $this->validateResponse($method, $action, $response);
        
        return $this->responseToObj($response);
    }

    public function getFinancePlans() :object{
        $method = 'GET';
        $action = 'PLANS';

        $response = $this->wrapper->get(self::PATHS[$method][$action]);
        
        $this->validateResponse($method, $action, $response);
        
        return $this->responseToObj($response);
    }

    public function postApplication(Application $application): object{
        $method = 'POST';
        $action = 'APPLICATION';

        $body = $application->getJsonPayload();
        
        $response = $this->wrapper->post(self::PATHS[$method][$action], $body);
        
        $this->validateResponse($method, $action, $response);

        return $this->responseToObj($response);
    }

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

    private function validateResponse($method, $action, $response){
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

    private function responseToObj(array $response):object{
        return json_decode(wp_remote_retrieve_body($response), false, 512, JSON_THROW_ON_ERROR);
    }
}
