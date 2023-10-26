<?php 
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Divido\Woocommerce\FinanceGateway\Proxies\MerchantApiPubProxy;
use Divido\Woocommerce\FinanceGateway\Wrappers\HttpApiWrapper;
use Divido\MerchantSDK\Exceptions\MerchantApiBadResponseException;
use Divido\Woocommerce\FinanceGateway\Exceptions\ResponseException;
use Divido\MerchantSDK\Models\Application;
use Divido\MerchantSDK\Models\ApplicationActivation;
use Divido\MerchantSDK\Models\ApplicationCancellation;
use Divido\MerchantSDK\Models\ApplicationRefund;

final class MerchantApiPubProxyTest extends TestCase
{
    
    /**
     * Tests the AddSecretHeader function
     *
     * @doesNotPerformAssertions
     * @return void
     */
    public function testAddSecretHeader():void{

        $proxy = new MerchantApiPubProxy('http://base-uri.com', 'api_key.0001');

        $mockWrapper = Mockery::mock(HttpApiWrapper::class);

        $newSecret = 'newSecret';

        $mockWrapper->shouldReceive('setHeader')
        ->once()
        ->withArgs([
            MerchantApiPubProxy::HEADER_KEYS['SHARED_SECRET'],
            $newSecret
        ]);

        $proxy->setWrapper($mockWrapper);

        $proxy->addSecretHeader($newSecret);
    }

    public function testGetHealthWithPositiveResponse():void{
        $proxy = new MerchantApiPubProxy('http://base-uri.com', 'api_key.0001');

        $response = [
            'response' => ['code' => MerchantApiPubProxy::EXPECTED_RESPONSE_CODES['GET']['HEALTH']],
            'body' => 'OK'
        ];
        $mockWrapper = Mockery::mock(HttpApiWrapper::class);

        $mockWrapper->shouldReceive('get')
        ->once()
        ->with(MerchantApiPubProxy::PATHS['GET']['HEALTH'])
        ->andReturns($response);

        $proxy->setWrapper($mockWrapper);

        $this->assertTrue($proxy->getHealth());
    }


    public function testGetHealthWithNegativeResponse():void{
        $proxy = new MerchantApiPubProxy('http://base-uri.com', 'api_key.0001');

        $response = [
            'response' => ['code' => 500],
            'body' => 'NOT OK'
        ];
        $mockWrapper = Mockery::mock(HttpApiWrapper::class);

        $mockWrapper->shouldReceive('get')
        ->once()
        ->with(MerchantApiPubProxy::PATHS['GET']['HEALTH'])
        ->andReturns($response);

        $proxy->setWrapper($mockWrapper);

        $this->assertFalse($proxy->getHealth());
    }

    public function testGetEnvironmentWithPositiveResponse():void{
        $proxy = new MerchantApiPubProxy('http://base-uri.com', 'api_key.0001');

        $responseBodyArr = ['data' => ['environment' => 'divido']];
        $response = [
            'response' => ['code' => MerchantApiPubProxy::EXPECTED_RESPONSE_CODES['GET']['ENVIRONMENT']],
            'body' => json_encode($responseBodyArr)
        ];
        $mockWrapper = Mockery::mock(HttpApiWrapper::class);

        $mockWrapper->shouldReceive('get')
        ->once()
        ->with(MerchantApiPubProxy::PATHS['GET']['ENVIRONMENT'])
        ->andReturns($response);

        $proxy->setWrapper($mockWrapper);

        $this->assertSame(
            $responseBodyArr['data']['environment'],
            $proxy->getEnvironment()->data->environment
        );
    }


    public function testGetFinancePlansWithPositiveResponse():void{
        $proxy = new MerchantApiPubProxy('http://base-uri.com', 'api_key.0001');

        $responseBodyArr = [
            'meta' => ['count' => 1],
            'data' => [
                [
                    'id' => uniqid(),
                    'active'=>true,
                    'description' => 'Some Finance Plan'
                ]
            ]
        ];
        $response = [
            'response' => ['code' => MerchantApiPubProxy::EXPECTED_RESPONSE_CODES['GET']['PLANS']],
            'body' => json_encode($responseBodyArr)
        ];
        $mockWrapper = Mockery::mock(HttpApiWrapper::class);

        $mockWrapper->shouldReceive('get')
        ->once()
        ->with(MerchantApiPubProxy::PATHS['GET']['PLANS'])
        ->andReturns($response);

        $proxy->setWrapper($mockWrapper);

        $this->assertSame(
            $responseBodyArr['data'][0]['id'],
            $proxy->getFinancePlans()->data[0]->id
        );
    }

    public function testPostApplicationWithPositiveResponse():void{
        $proxy = new MerchantApiPubProxy('http://base-uri.com', 'api_key.0001');

        $application = (new Application())
            ->withFinancePlanId('123');
        
        $responseBodyArr = [
            'data' => [
                'id' => uniqid(),
                'token'=>'7',
                'current_status' => 'PROPOSAL'
            ]
        ];
        $response = [
            'response' => ['code' => MerchantApiPubProxy::EXPECTED_RESPONSE_CODES['POST']['APPLICATION']],
            'body' => json_encode($responseBodyArr)
        ];
        $mockWrapper = Mockery::mock(HttpApiWrapper::class);

        $mockWrapper->shouldReceive('post')
        ->once()
        ->withArgs([
            MerchantApiPubProxy::PATHS['POST']['APPLICATION'],
            $application->getJsonPayload()
        ])
        ->andReturns($response);

        $proxy->setWrapper($mockWrapper);

        $this->assertSame(
            $responseBodyArr['data']['id'],
            $proxy->postApplication($application)->data->id
        );
    }

    public function testPostActivationWithPositiveResponse():void{
        $proxy = new MerchantApiPubProxy('http://base-uri.com', 'api_key.0001');

        $applicationId = uniqid();

        $activation = (new ApplicationActivation())
            ->withOrderItems([
                ['name' => 'An Item', 'quantity'=>1, 'price'=>10000]
            ]);
        
        $responseBodyArr = [
            'data' => [
                'id' => uniqid(),
                'amount'=>10000,
                'status' => 'AWAITING-ACTIVATION'
            ]
        ];
        $response = [
            'response' => ['code' => MerchantApiPubProxy::EXPECTED_RESPONSE_CODES['POST']['ACTIVATION']],
            'body' => json_encode($responseBodyArr)
        ];
        $mockWrapper = Mockery::mock(HttpApiWrapper::class);

        $mockWrapper->shouldReceive('post')
        ->once()
        ->withArgs([
            sprintf(MerchantApiPubProxy::PATHS['POST']['ACTIVATION'], $applicationId),
            $activation->getJsonPayload()
        ])
        ->andReturns($response);

        $proxy->setWrapper($mockWrapper);

        $this->assertSame(
            $responseBodyArr['data']['id'],
            $proxy->postActivation($applicationId, $activation)->data->id
        );
    }

    public function testPostCancellationWithPositiveResponse():void{
        $proxy = new MerchantApiPubProxy('http://base-uri.com', 'api_key.0001');

        $applicationId = uniqid();

        $cancellation = (new ApplicationCancellation())
            ->withOrderItems([
                ['name' => 'An Item', 'quantity'=>1, 'price'=>10000]
            ]);
        
        $responseBodyArr = [
            'data' => [
                'id' => uniqid(),
                'amount'=>10000,
                'status' => 'AWAITING-CANCELLATION'
            ]
        ];
        $response = [
            'response' => ['code' => MerchantApiPubProxy::EXPECTED_RESPONSE_CODES['POST']['CANCELLATION']],
            'body' => json_encode($responseBodyArr)
        ];
        $mockWrapper = Mockery::mock(HttpApiWrapper::class);

        $mockWrapper->shouldReceive('post')
        ->once()
        ->withArgs([
            sprintf(MerchantApiPubProxy::PATHS['POST']['CANCELLATION'], $applicationId),
            $cancellation->getJsonPayload()
        ])
        ->andReturns($response);

        $proxy->setWrapper($mockWrapper);

        $this->assertSame(
            $responseBodyArr['data']['id'],
            $proxy->postCancellation($applicationId, $cancellation)->data->id
        );
    }

    public function testPostRefundWithPositiveResponse():void{
        $proxy = new MerchantApiPubProxy('http://base-uri.com', 'api_key.0001');

        $applicationId = uniqid();
        $newOrderItem = ['name' => 'An Item', 'quantity'=>1, 'price'=>10000];

        $refund = (new ApplicationRefund())
            ->withOrderItems([$newOrderItem]);
        
        $responseBodyArr = [
            'data' => [
                'id' => $applicationId,
                'token'=>uniqid(),
                'current_status' => 'PROPOSAL',
                'order_items' => [$newOrderItem]
            ]
        ];
        $response = [
            'response' => ['code' => MerchantApiPubProxy::EXPECTED_RESPONSE_CODES['POST']['REFUND']],
            'body' => json_encode($responseBodyArr)
        ];
        $mockWrapper = Mockery::mock(HttpApiWrapper::class);

        $mockWrapper->shouldReceive('post')
        ->once()
        ->withArgs([
            sprintf(MerchantApiPubProxy::PATHS['POST']['REFUND'], $applicationId),
            $refund->getJsonPayload()
        ])
        ->andReturns($response);

        $proxy->setWrapper($mockWrapper);

        $this->assertSame(
            $responseBodyArr['data']['id'],
            $proxy->postRefund($applicationId, $refund)->data->id
        );
    }

    public function testApplicationUpdateWithPositiveResponse():void{
        $proxy = new MerchantApiPubProxy('http://base-uri.com', 'api_key.0001');

        $applicationId = uniqid();
        $newItem = ['name' => 'Different Item', 'quantity'=>1, 'price'=>10000];

        $application = (new Application())
            ->withId($applicationId)
            ->withOrderItems([$newItem]);
        
        $responseBodyArr = [
            'data' => [
                'id' => $applicationId,
                'token' => uniqid(),
                'order_items' => [(object)$newItem],
                'current_status' => 'PROPOSAL'
            ]
        ];
        $response = [
            'response' => ['code' => MerchantApiPubProxy::EXPECTED_RESPONSE_CODES['PATCH']['APPLICATION']],
            'body' => json_encode($responseBodyArr)
        ];
        $mockWrapper = Mockery::mock(HttpApiWrapper::class);

        $mockWrapper->shouldReceive('patch')
        ->once()
        ->withArgs([
            sprintf(MerchantApiPubProxy::PATHS['PATCH']['APPLICATION'], $applicationId),
            $application->getPayload()
        ])
        ->andReturns($response);
        
        $proxy->setWrapper($mockWrapper);

        $this->assertSame(
            $newItem,
            (array) $proxy->updateApplication($application)->data->order_items[0]
        );
    }

    public function testValidateResponseWithBadResponseException():void{
        $proxy = new MerchantApiPubProxy('http://base-uri.com', 'api_key.0001');

        $responseBodyArr = [
            'error' => true,
            'code' => 500001,
            'message' => 'An unexpected error has occurred'
        ];
        $response = [
            'response' => ['code' => 500],
            'body' => json_encode($responseBodyArr)
        ];
        $mockWrapper = Mockery::mock(HttpApiWrapper::class);

        $mockWrapper->shouldReceive('get')
        ->once()
        ->with(MerchantApiPubProxy::PATHS['GET']['ENVIRONMENT'])
        ->andReturns($response);

        $proxy->setWrapper($mockWrapper);

        $this->expectException(MerchantApiBadResponseException::class);
        $proxy->getEnvironment();
    }

    public function testValidateResponseWithMalformedResponse():void{
        $proxy = new MerchantApiPubProxy('http://base-uri.com', 'api_key.0001');

        $responseBodyArr = ['data' => 'An unexpected error has occurred'];
        $response = [
            'body' => json_encode($responseBodyArr)
        ];
        $mockWrapper = Mockery::mock(HttpApiWrapper::class);

        $mockWrapper->shouldReceive('get')
        ->once()
        ->with(MerchantApiPubProxy::PATHS['GET']['ENVIRONMENT'])
        ->andReturns($response);

        $proxy->setWrapper($mockWrapper);

        $this->expectException(ResponseException::class);
        try{
            $proxy->getEnvironment();
        } catch (ResponseException $e){
            $this->assertSame(
                "There was an unexpected problem with the response received from the request",
                $e->getMessage()
            );
            throw $e;
        }
    }

    public function testValidateResponseWithUnexpectedResponse():void{
        $proxy = new MerchantApiPubProxy('http://base-uri.com', 'api_key.0001');

        $responseBodyArr = ['data' => 'An unexpected error has occurred'];
        $response = [
            'response' => ['code' => 500],
            'body' => json_encode($responseBodyArr)
        ];
        $mockWrapper = Mockery::mock(HttpApiWrapper::class);

        $mockWrapper->shouldReceive('get')
        ->once()
        ->with(MerchantApiPubProxy::PATHS['GET']['ENVIRONMENT'])
        ->andReturns($response);

        $proxy->setWrapper($mockWrapper);

        $this->expectException(ResponseException::class);
        try{
            $proxy->getEnvironment();
        } catch (ResponseException $e){
            $this->assertSame(
                "An unexpected error occurred when contacting the Merchant API Pub",
                $e->getMessage()
            );
            throw $e;
        }
    }

}