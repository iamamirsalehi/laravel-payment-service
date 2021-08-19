<?php

namespace App\Services\Payment\PaymentChain\IDPayChain\Handlers\Verify;


use App\Repository\Contracts\Payment\PaymentRepositoryInterface;
use App\Services\Payment\PaymentChain\IDPayChain\Contracts\AbstractHandler;
use App\Services\Payment\PaymentChain\IDPayChain\Exceptions\PaymentDoesNotExistException;
use App\Services\Payment\PaymentChain\IDPayChain\Exceptions\PaymentMustBeExistedException;
use App\Utilities\Log;

class PaymentExistanceHandler extends AbstractHandler
{
    public function handle(array $data = null)
    {
        if (!isset($data['order_id'])) {
            throw new PaymentMustBeExistedException('[order_id] key must be existed in the data');
        }

        $paymentRepo = resolve(PaymentRepositoryInterface::class);

        $payment = $paymentRepo->findBy([
            'factor_number' => $data['order_id'],
        ], null, 'first');

        if (!$payment) {
            Log::action([
                'type' => 'warning',
                'user_id' => $payment->user->id,
                'method_address' => 'App\Services\Payment\PaymentChain\IDPayChain\Pay\PaymentExistanceHandler::handle',
                'action' => 'user with(ID=' . $payment->user->id . ') in payment tried to pay with an unverified bank card',
            ]);

            throw new PaymentDoesNotExistException('همچین پرداختی انجام نشده است');
        }

        $data += ['payment' => $payment];

        return $this->next($data);
    }
}