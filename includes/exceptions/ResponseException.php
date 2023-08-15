<?php

namespace Divido\Woocommerce\FinanceGateway\Exceptions;

class ResponseException extends \Exception{

    private $method;
    private $action;

    public function __construct(string $message, ?int $code=0, ?string $method=null, ?string $action=null){
        $this->method = $method;
        $this->action = $action;

        parent::__construct($message, $code);
    }

    public function getMethod(){
        return $this->method;
    }

    public function getAction(){
        return $this->action;
    }


}