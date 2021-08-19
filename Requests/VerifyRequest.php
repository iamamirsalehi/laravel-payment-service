<?php

namespace App\Services\Payment\Requests;

use App\Models\Payment;

class VerifyRequest
{
    public function __construct(protected Payment $payment, protected string|int $orderId, protected string|null $cardNo, protected string|int $id)
    {
    }

    public function getId()
    {
        return $this->id;
    }

    public function getOrderId()
    {
        return $this->orderId;
    }

    public function getCardNo()
    {
        return $this->cardNo;
    }

    public function getPayment()
    {
        return $this->payment;
    }
}