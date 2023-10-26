<?php 
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Divido\Woocommerce\FinanceGateway\Models\ShortPlan;

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

    private function getSomeFinancePlans(){
        return [
            (object)[
                'id' => uniqid('1_'), 
                'description' => 'Plan 1',
                'active' => true, 
                'credit_amount' => (object)[
                    'minimum_amount' => 0, 
                    'maximum_amount' => 100000
                ]
            ],
            (object)[
                'id' => uniqid('2_'), 
                'description' => 'Plan 2',
                'active' => true, 
                'credit_amount' => (object)[
                    'minimum_amount' => 10000, 
                    'maximum_amount' => 2000000
                ]
            ],
            (object)[
                'id' => uniqid('3_'), 
                'description' => 'Plan 3',
                'active' => false, 
                'credit_amount' => (object)[
                    'minimum_amount' => 0, 
                    'maximum_amount' => 2000000
                ]
            ],
            (object)[
                'id' => uniqid('4_'), 
                'description' => 'Plan 4',
                'active' => false, 
                'credit_amount' => (object)[
                    'minimum_amount' => 25000, 
                    'maximum_amount' => 1500000
                ]
            ]
        ];
    }

    public function testGetAllFinancesViaTransient():void{

        set_transient('api_key', 'api_key');
        $this->gateway->api_key = 'api_key';
        $financePlans = $this->getSomeFinancePlans();
        set_transient('finances', $financePlans);
        $this->assertCount(
            count($financePlans),
            $this->gateway->get_all_finances()
        );
    }

    public function testFilterPlansByRefineList():void{
        $plans = [
            new ShortPlan(uniqid('1_'), 'Plan 1', 0, 1000000, true),
            new ShortPlan(uniqid('2_'), 'Plan 2', 10000, 2000000, false),
            new ShortPlan(uniqid('3_'), 'Plan 3', 0, 2000000, true),
            new ShortPlan(uniqid('4_'), 'Plan 4', 25000, 1500000, false)
        ];

        $this->gateway->settings['showFinanceOptionSelection'] = [
            $plans[0]->getId(),
            $plans[2]->getId()
        ];

        $refinedPlans = $this->gateway->filterPlansByRefineList($plans);

        $this->assertCount(2, $refinedPlans);
    }

    public function testFilterPlansByActive():void{
        $plans = [
            new ShortPlan(uniqid('1_'), 'Plan 1', 0, 1000000, true),
            new ShortPlan(uniqid('2_'), 'Plan 2', 10000, 2000000, false),
            new ShortPlan(uniqid('3_'), 'Plan 3', 0, 2000000, true),
            new ShortPlan(uniqid('4_'), 'Plan 4', 25000, 1500000, false)
        ];

        $refinedPlans = $this->gateway->filterPlansByActive($plans);

        $this->assertCount(2, $refinedPlans);
    }

    public function testGetShortPlansArray():void{
        $this->gateway->finance_options = $this->getSomeFinancePlans();

        $this->gateway->settings['showFinanceOptions'] = 'selection';
        $this->gateway->settings['showFinanceOptionSelection'] = [
            $this->gateway->finance_options[0]->id,
            $this->gateway->finance_options[2]->id
        ];

        $shortPlans = $this->gateway->get_short_plans_array();

        $this->assertCount(1, $shortPlans);

    }

    public function testGetShortPlansArrayWithInactive():void{
        $this->gateway->finance_options = $this->getSomeFinancePlans();

        $this->gateway->settings['showFinanceOptions'] = 'selection';
        $this->gateway->settings['showFinanceOptionSelection'] = [
            $this->gateway->finance_options[0]->id,
            $this->gateway->finance_options[2]->id
        ];

        $shortPlans = $this->gateway->get_short_plans_array(false);

        $this->assertCount(2, $shortPlans);

    }

    public function testGetUnrefinedShortPlansArray():void{
        $this->gateway->finance_options = $this->getSomeFinancePlans();

        $shortPlans = $this->gateway->get_short_plans_array(true, false);

        $this->assertCount(2, $shortPlans);
    }

    public function testInitFormFieldsWithoutApiKey():void{
        $this->gateway->api_key = null;
        
        $this->gateway->init_form_fields();

        $this->assertCount(2, $this->gateway->form_fields);
    }

    public function testInitFormFieldsWithApiKey():void{
        $this->gateway->api_key = uniqid('testing_');
        $this->gateway->finance_options = $this->getSomeFinancePlans();

        $this->gateway->init_form_fields();

        $this->assertCount(26, $this->gateway->form_fields);
    }

}