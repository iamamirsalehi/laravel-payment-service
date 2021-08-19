<?php

namespace App\Services\Payment\Providers;

use App\Models\Payment;
use App\Repository\Contracts\EloquentTransactionInterface;
use App\Repository\Contracts\Payment\PaymentRepositoryInterface;
use App\Repository\Contracts\RequestedCoin\RequestedCoinRepositoryInterface;
use App\Services\Payment\Contracts\PayableInterface;
use App\Services\Payment\Contracts\VerifiableInterface;
use App\Services\Payment\Exceptions\CouldNotGoToGatwayException;
use App\Services\Payment\PaymentChain\IDPayChain\Exceptions\PaymentFailedException;
use App\Services\Payment\Requests\PayRequest;
use App\Services\Payment\Requests\VerifyRequest;
use App\Utilities\API\Money;
use App\Utilities\Log;
use Iamamirsalehi\LaravelBalance\Services\Balance\BalanceService;
use Illuminate\Support\Str;

class IDPayProvider implements PayableInterface, VerifiableInterface
{
    public $success = "100";

    public function pay(PayRequest $dataRequest)
    {
        $paymentRepo = resolve(PaymentRepositoryInterface::class);

        $factor_number = Str::random(32);

        $params = [
            'order_id' => $factor_number,
            'amount' => $dataRequest->getAmount(),
            'name' => $dataRequest->getUser()->full_name,
            'callback' => route("payment.callback"),
        ];

        $response = $this->checkPayProccess(config('services.id_pay.api_key'), $params);

        if ($response['httpCode'] != 201) {
            Log::action([
                'type' => 'warning',
                'user_id' => $dataRequest->getUser()->id,
                'method_address' => 'App\Services\Payment\Providers\IDPayProvider::pay',
                'action' => 'user (ID=' . $dataRequest->getUser()->id . ') with amount of ' . $dataRequest->getAmount() . 'IRR pay and error (' . $response['result']->error_message . ')',
            ]);

            throw new CouldNotGoToGatwayException($response['result']->error_message);
        }

        $stored_payment = $paymentRepo->store([
            'factor_number' => $factor_number,
            'amount' => $dataRequest->getAmount(),
            'bank_account_id' => $dataRequest->getBankAccount()->id,
            'user_id' => $dataRequest->getUser()->id
        ]);

        if (!$stored_payment) {
            Log::action([
                'type' => 'warning',
                'user_id' => $dataRequest->getUser()->id,
                'method_address' => 'App\Services\Payment\Providers\IDPayProvider::pay',
                'action' => 'user (ID=' . $dataRequest->getUser()->id . ') with amount of ' . $dataRequest->getAmount() . 'IRR pay, Could not send to gateway because Payment could not be stored in database',
            ]);

            throw new CouldNotGoToGatwayException('مشکلی در زمان ارسال به درگاه پرداخت به وجود آمد لطفا دوباره امتحان کنید');
        }

        Log::action([
            'type' => 'info',
            'user_id' => $dataRequest->getUser()->id,
            'method_address' => 'App\Services\Payment\Providers\IDPayProvider::pay',
            'action' => 'user (ID=' . $dataRequest->getUser()->id . ') is ready to pay with amount of ' . $dataRequest->getAmount() . 'IRR',
        ]);

        return redirect()->away($response['result']->link)->send();
    }

    public function verify(VerifyRequest $verifyRequest)
    {
        $paymentRepo = resolve(PaymentRepositoryInterface::class);
        $eloquentRepo = resolve(EloquentTransactionInterface::class);
        $requestedCoinRepo = resolve(RequestedCoinRepositoryInterface::class);

        $params = [
            'id' => $verifyRequest->getId(),
            'order_id' => $verifyRequest->getOrderId(),
        ];

        $result = $this->verifyPayment(config('services.id_pay.api_key'), $params)['result'];

        $response = [
            "msg" => null,
            "status" => false,
            "tracking_code_developer" => null,
            "tracking_code" => null,
            "card" => null,
            "card_hashed" => null,
            "amount" => null,
            "gateway" => "idpay",
        ];

        if (isset($result->error_code)) {
            $response['msg'] = $result->error_message;

            $paymentRepo->updateBy([
                'factor_number' => $verifyRequest->getOrderId(),
            ], [
                'is_success_payment' => Payment::UNPAID
            ]);

            throw new PaymentFailedException($response['msg']);
        }


        $response['msg'] = $this->translate($result->status);
        $response['tracking_code_developer'] = $result->id;
        $response['tracking_code'] = $result->track_id;
        $response['amount'] = $result->payment->amount;


        if ($result->status == $this->success) {
            $response['tracking_code'] = $result->payment->track_id;
            $response['card'] = $result->payment->card_no;
            $response['card_hashed'] = $result->payment->hashed_card_no;
            $response['status'] = true;

            $paymentRepo->updateBy([
                'factor_number' => $verifyRequest->getOrderId(),
            ], [
                'tracking_code' => $result->payment->track_id,
                'is_success_payment' => Payment::PAID
            ]);

            $verified_payment = $paymentRepo->findBy([
                'user_id' => $verifyRequest->getPayment()->user_id,
                'amount' => $verifyRequest->getPayment()->amount,
                'factor_number' => $verifyRequest->getOrderId(),
                'is_success_payment' => Payment::PAID
            ], null, 'first');

            try {
                $eloquentRepo->beginTransaction();

                $deposited_price = BalanceService::deposit([
                    'user_id' => (int)$verified_payment->user_id,
                    'coin_id' => (int)Money::getIRR()->id,
                    'price' => (int)$verified_payment->amount
                ])->handle();

                $eloquentRepo->commit();
            } catch (\Exception $e) {
                $eloquentRepo->rollback();
                Log::action([
                    'type' => 'error',
                    'user_id' => $verified_payment->user_id,
                    'method_address' => 'App\Http\Controllers\Admin\Balance\PaymentController::verify',
                    'action' => 'deposit of the user with(ID=' . $verified_payment->user_id . ') with amount of (' . $verified_payment->amount . 'IRR) failed in payment',
                ]);

                return redirect()->route('payment.callback.form')->with('failed', $e->getMessage());
            }

            $requestedCoinRepo->store([
                'status' => $requestedCoinRepo::ACCEPTED,
                'amount' => (int)$verified_payment->amount,
                'user_id' => (int)$verified_payment->user_id,
                'coin_id' => Money::getIRR()->id,
                'type' => $requestedCoinRepo::DEPOSIT,
            ]);

            Log::action([
                'type' => 'info',
                'user_id' => $verified_payment->user_id,
                'method_address' => 'App\Http\Controllers\Admin\Balance\PaymentController::verify',
                'action' => 'the amount of  (' . $verified_payment->amount . 'IRR) is deposited for user with (ID=' . $verified_payment->user_id . ')',
            ]);
        }

        return [
            'user_id' => $verified_payment->user_id,
            'amount' => $verified_payment->amount,
        ];
    }

    private function checkPayProccess(string $apiKey, array $params): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.idpay.ir/v1.1/payment');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            "X-API-KEY: {$apiKey}",
//            "X-SANDBOX: 1"
        ));

        $result = curl_exec($ch);
        $result = json_decode($result);

        # get http code
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'result' => $result,
            'httpCode' => $httpCode,
        ];
    }

    private function verifyPayment(string $apiKey, array $params): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.idpay.ir/v1.1/payment/verify');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json",
            "X-API-KEY: {$apiKey}",
//            "X-SANDBOX: 1",
        ));

        $result = curl_exec($ch);
        $result = json_decode($result);
        curl_close($ch);

        return [
            'result' => $result,
        ];
    }

    private function translate($code): string
    {
        $msg = '';
        switch ($code) {
            case "1":
                $msg = 'پرداخت انجام نشده است';
                break;
            case "2":
                $msg = 'پرداخت ناموفق بوده است';
                break;
            case "3":
                $msg = 'خطا رخ داده است';
                break;
            case "4":
                $msg = 'بلوکه شده';
                break;
            case "5":
                $msg = 'برگشت به پرداخت کننده';
                break;
            case "6":
                $msg = 'برگشت خورده سیستمی';
                break;
            case "10":
                $msg = 'در انتظار تایید پرداخت';
                break;
            case "100":
                $msg = 'پرداخت تایید شده است';
                break;
            case "101":
                $msg = 'پرداخت قبلا تایید شده است';
                break;
            case "200":
                $msg = 'به دریافت کننده واریز شد';
                break;
        }
        return $msg;
    }
}
