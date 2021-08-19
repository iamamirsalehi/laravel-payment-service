<?php

namespace App\Services\Payment\PaymentChain\IDPayChain\Handlers\Verify;


use App\Services\Payment\PaymentChain\IDPayChain\Contracts\AbstractHandler;
use App\Services\Payment\PaymentChain\IDPayChain\Exceptions\BankAccountMustBeExistedException;
use App\Services\Payment\PaymentChain\IDPayChain\Exceptions\BankAccountVerificationException;
use App\Services\Payment\PaymentChain\IDPayChain\Exceptions\PaymentMustBeExistedException;
use App\Utilities\Log;

class BankAccountVerificationHandler extends AbstractHandler
{
    public function handle(array $data = null)
    {
        if (!isset($data['payment'])) {
            throw new PaymentMustBeExistedException('[payment] key must be existed in the data');
        }

        if (!$this->verifyCardNumber($data['card_no'], $data['payment'])) {
            Log::action([
                'type' => 'warning',
                'user_id' => $data['payment']->user->id,
                'method_address' => 'App\Services\Payment\PaymentChain\IDPayChain\Pay\BankAccountVerification::handle',
                'action' => 'user with(ID=' . $data['payment']->user->id . ') in payment tried to pay with an unverified bank card',
            ]);

            throw new BankAccountVerificationException('پرداخت انجام نشد (شماره کارت پرداختی با شماره کارت موجود در حساب کاربری تطابق ندارند)');
        }

        return $this->next($data);
    }

    private function verifyCardNumber($card_no, $payment)
    {
        $bank_account = $payment->bankAccount;

        $first_part_of_bank_account = substr($bank_account->bank_account_cart_number, 0, 6);

        $second_part_of_bank_account = substr($bank_account->bank_account_cart_number, 12, 15);

        $card_number = $first_part_of_bank_account . '******' . $second_part_of_bank_account;

        return $card_no == $card_number;
    }
}