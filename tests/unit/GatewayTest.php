<?php 
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GatewayTest extends TestCase
{
    private WC_Gateway_Finance $gateway;
    
    public function setUp():void{
        $this->gateway = new WC_Gateway_Finance();
    }
    
    public function testConstruct():void{
        $this->assertSame(
            'finance',
            $this->gateway->id
        );
    }

}