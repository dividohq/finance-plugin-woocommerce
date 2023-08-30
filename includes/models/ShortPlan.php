<?php

namespace Divido\Woocommerce\FinanceGateway\Models;

class ShortPlan{

    private string $id;

    private string $name;

    private bool $active;

    public function __construct(
        string $id,
        string $name,
        bool $active
    ){
        $this->id = $id;
        $this->name = $name;
        $this->active = $active;
    }

    /**
     * Get the id of the plan
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get the name of the plan
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get whether the plan is active
     */
    public function isActive(): bool
    {
        return $this->active;
    }
}