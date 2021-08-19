<?php

namespace App\Services\Payment;

use App\Services\Payment\Exceptions\GatewayDoesNotExistsException;
use App\Services\Payment\Requests\PayRequest;
use App\Services\Payment\Requests\VerifyRequest;

class PaymentService
{
    public const IDPAY = 'IDPayProvider';

    public const Vandar = 'VandarProvider';

    public function __construct(protected PayRequest|VerifyRequest $dataRequest, protected string $gateway = self::IDPAY)
    {
    }

    private function findGateway()
    {
        $namespace = "App\Services\Payment\Providers\\" . $this->gateway;

        if (!class_exists($namespace)) {
            throw new GatewayDoesNotExistsException('درگاه مورد نظر وجود ندارد');
        }

        return (new $namespace);
    }

    public function sendToGateway()
    {
        return $this->findGateway()->pay($this->dataRequest);
    }

    public function verify()
    {
        return $this->findGateway()->verify($this->dataRequest);
    }
}