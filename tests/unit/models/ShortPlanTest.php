<?php 
declare(strict_types=1);

use Divido\Woocommerce\FinanceGateway\Models\ShortPlan;
use PHPUnit\Framework\TestCase;

final class ShortPlanTest extends TestCase
{

    public function testGetId():void{
        $id = uniqid();
        $plan = new ShortPlan(
            $id,
            'plan name',
            0,
            100,
            true
        );

        $this->assertEquals(
            $id,
            $plan->getId()
        );
    }

    public function testGetName():void{
        $planName = 'Plan Name';
        $plan = new ShortPlan(
            uniqid(),
            $planName,
            0,
            100,
            true
        );

        $this->assertEquals(
            $planName,
            $plan->getName()
        );
    }

    public function testIsActive():void{
        $active = true;
        $plan = new ShortPlan(
            uniqid(),
            'plan name',
            0,
            100,
            $active
        );

        $this->assertEquals(
            $active,
            $plan->isActive()
        );
    }

    public function testGetCreditMinimum():void{
        $creditMin = 50;
        $plan = new ShortPlan(
            uniqid(),
            'plan name',
            $creditMin,
            100,
            true
        );

        $this->assertEquals(
            $creditMin,
            $plan->getCreditMinimum()
        );
    }

    public function testGetCreditMaximum():void{
        $creditMax = 20000;
        $plan = new ShortPlan(
            uniqid(),
            'plan name',
            0,
            $creditMax,
            true
        );

        $this->assertEquals(
            $creditMax,
            $plan->getCreditMaximum()
        );
    }

}