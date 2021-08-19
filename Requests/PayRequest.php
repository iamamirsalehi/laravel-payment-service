<?php

namespace App\Services\Payment\Requests;

use App\Models\BankAccount;
use App\Models\User;

class PayRequest
{
    public function __construct(protected int $amount,
                                protected BankAccount $bankAccount,
                                protected User $user,
                                protected string|null $description = null)
    {
    }

    public function getAmount()
    {
        return $this->amount;
    }

    public function getBankAccount()
    {
        return $this->bankAccount;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getDescription()
    {
        return $this->description;
    }
}