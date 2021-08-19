<?php

namespace App\Services\Payment\Providers;

use App\Services\Payment\Contracts\PayableInterface;
use App\Services\Payment\Contracts\VerifiableInterface;
use App\Services\Payment\Requests\PayRequest;
use App\Services\Payment\Requests\VerifyRequest;

class VandarProvider implements PayableInterface, VerifiableInterface
{

    public function pay(PayRequest $dataRequest)
    {
        // TODO: Implement pay() method.
    }

    public function verify(VerifyRequest $verifyRequest)
    {
        // TODO: Implement verify() method.
    }
}