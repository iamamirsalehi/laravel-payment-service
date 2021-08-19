<?php

namespace App\Services\Payment\PaymentChain\IDPayChain\Contracts;

interface Handler
{
    public function setNext(Handler $handler);

    public function handle(array $data = null);

    public function next(array $data = null);
}