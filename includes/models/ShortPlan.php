<?php

namespace Divido\Woocommerce\FinanceGateway\Models;

class ShortPlan{

    private string $id;

    private string $name;

    private int $creditMinimum;

    private int $creditMaximum;

    private bool $active;

    public function __construct(
        string $id,
        string $name,
        int $creditMinimum,
        int $creditMaximum,
        bool $active
    ){
        $this->id = $id;
        $this->name = $name;
        $this->creditMinimum = $creditMinimum;
        $this->creditMaximum = $creditMaximum;
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

    /**
     * Get the minimum credit value for this plan to be applicable
     */
    public function getCreditMinimum(): int
    {
        return $this->creditMinimum;
    }

    /**
     * Get the maximum credit value for this plan to be applicable
     */
    public function getCreditMaximum(): int
    {
        return $this->creditMaximum;
    }
}