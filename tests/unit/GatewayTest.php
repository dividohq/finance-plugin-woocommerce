<?php 
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GatewayTest extends TestCase
{
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