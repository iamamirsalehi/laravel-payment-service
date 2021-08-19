<?php

namespace App\Services\Payment\PaymentChain\IDPayChain\Contracts;

abstract class AbstractHandler implements Handler
{
    protected $nextHandler;

    public function setNext(Handler $handler)
    {
        $this->nextHandler = $handler;
    }

    public function next(array $data = null)
    {
        if ($this->nextHandler) {
            // go to next one:
            return $this->nextHandler->handle($data);
        }
    }
}