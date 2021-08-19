<?php

namespace App\Services\Payment\Contracts;

use App\Services\Payment\Requests\VerifyRequest;

interface VerifiableInterface
{
    public function verify(VerifyRequest $verifyRequest);
}