<?php

namespace App\Services\Payment\PaymentChain\IDPayChain\Handlers\Pay;

use App\Services\Payment\PaymentChain\IDPayChain\Contracts\AbstractHandler;
use App\Services\Payment\PaymentChain\IDPayChain\Exceptions\AmountInvalidException;
use App\Services\Payment\PaymentChain\IDPayChain\Exceptions\AmountMustBeExistedException;
use App\Utilities\Log;

class AmountValidationHandler extends AbstractHandler
{
    public function handle(array $data = null)
    {
        if (!isset($data['amount'])) {
            throw new AmountMustBeExistedException('[amount] key must be existed in the data');
        }

        $balance_setting = $data['balance_setting'];

        if ($data['amount'] < $balance_setting['min_deposit'] or $data['amount'] > $balance_setting['max_deposit']) {
            Log::action([
                'type' => 'warning',
                'user_id' => $data['user']->id,
                'method_address' => 'App\Services\Payment\PaymentChain\IDPayChain\Handlers\AmountValidationHandler::handle',
                'action' => 'amount (' . $data['amount'] . ') is not valid',
            ]);

            throw  new AmountInvalidException('مبلغ برای واریز مجاز نمی باشد و باید بین ' . number_format($balance_setting['min_deposit'] / 10) . ' و '  . number_format($balance_setting['max_deposit'] / 10) . ' تومان ' . ' باشد');
        }

        return $this->next($data);
    }
}