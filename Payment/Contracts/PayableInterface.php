<?php

namespace App\Services\Payment\Contracts;

use App\Services\Payment\Requests\PayRequest;

interface PayableInterface
{
    public function pay(PayRequest $dataRequest);
}