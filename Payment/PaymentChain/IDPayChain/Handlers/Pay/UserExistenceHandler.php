<?php

namespace App\Services\Payment\PaymentChain\IDPayChain\Handlers\Pay;

use App\Services\Payment\PaymentChain\IDPayChain\Contracts\AbstractHandler;
use App\Services\Payment\PaymentChain\IDPayChain\Exceptions\UserInvalidExistenceException;
use App\Services\Payment\PaymentChain\IDPayChain\Exceptions\UserMustBeExistedException;
use App\Utilities\Log;

class UserExistenceHandler extends AbstractHandler
{
    public function handle(array $data = null)
    {
        if (!isset($data['user'])) {
            throw new UserMustBeExistedException('[user] key must be existed in the data');
        }

        if (is_null($data['user']) or empty($data['user'])) {
            Log::action([
                'type' => 'warning',
                'user_id' => $data['ip'],
                'method_address' => 'App\Services\Payment\PaymentChain\IDPayChain\Handlers\UserExistenceHandler::handle',
                'action' => 'requested with wrong token',
            ]);

            throw new UserInvalidExistenceException('کاربری با این مشخصات پیدا نشد');
        }

        return $this->next($data);
    }
}