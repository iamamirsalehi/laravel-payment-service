<?php

namespace App\Services\Payment\PaymentChain\IDPayChain\Handlers\Pay;

use App\Services\Payment\PaymentChain\IDPayChain\Contracts\AbstractHandler;
use App\Services\Payment\PaymentChain\IDPayChain\Exceptions\BalanceInvalidSettingException;
use App\Services\Payment\PaymentChain\IDPayChain\Exceptions\LastSettingMustBeExistedException;
use App\Utilities\Log;

class BalanceSettingHandler extends AbstractHandler
{
    public function handle(array $data = null)
    {
        if (!isset($data['balance_setting'])) {
            throw new LastSettingMustBeExistedException('[balance setting] key must be existed in the data');
        }

        if (is_null($data['balance_setting'])) {
            Log::action([
                'type' => 'warning',
                'user_id' => $data['user']->id,
                'method_address' => 'App\Services\Payment\PaymentChain\IDPayChain\Pay\BalanceSettingHandler::handle',
                'action' => 'IRR setting does not exist',
            ]);

            throw new BalanceInvalidSettingException('مشکلی برای واریز ریالی وجود دارد لطفا بعدا دوباره تلاش کنید');
        }

        return $this->next($data);
    }
}