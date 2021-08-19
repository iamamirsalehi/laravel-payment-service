<?php

namespace App\Services\Payment\PaymentChain\IDPayChain\Handlers\Pay;

use App\Repository\Contracts\BankAccount\BankAccountRepositoryInterface;
use App\Services\Payment\PaymentChain\IDPayChain\Contracts\AbstractHandler;
use App\Services\Payment\PaymentChain\IDPayChain\Exceptions\BankAccountMustBeExistedException;
use App\Services\Payment\PaymentChain\IDPayChain\Exceptions\InvalidBankAccountException;
use App\Utilities\Log;

class BankAccountValidationHandler extends AbstractHandler
{
    public function handle(array $data = null)
    {
        if (!isset($data['bank_account'])) {
            throw new BankAccountMustBeExistedException('[bank account] must be existed');
        }

        $bankAccountCondition = $this->checkUserBankAccountIsValid($data['bank_account'], $data['user']->id);
        if (!$bankAccountCondition) {
            Log::action([
                'type' => 'warning',
                'user_id' => $data['user']->id,
                'method_address' => 'App\Services\Payment\PaymentChain\IDPayChain\Handlers\BankAccountValidationHandler::handle',
                'action' => 'user (ID=' . $data['user']->id . ') wanted to pay with unverified bank card (' . $data['bank_account'] . ')',
            ]);

            throw new InvalidBankAccountException('اطلاعات بانکی متعبر نیست');
        }

        return $bankAccountCondition;
    }

    private function checkUserBankAccountIsValid($bank_account_number, $user_id)
    {
        $bankAccountRepository = resolve(BankAccountRepositoryInterface::class);

        $user_bank_account = $bankAccountRepository->findBy([
            'bank_account_cart_number' => $bank_account_number,
            'verified_by' => $bankAccountRepository::VERIFIED
        ], null, 'first');

        if (!is_null($user_bank_account)) {
            if ($user_bank_account->user_id != $user_id)
                return false;

            return $user_bank_account;
        }

        return false;
    }

}