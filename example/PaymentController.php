<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\Payment\PayRequest;
use App\Http\Requests\Trade\BalanceLastSetting;
use App\Repository\Contracts\Payment\PaymentRepositoryInterface;
use App\Repository\Contracts\SanctumPersonalAccessToken\PersonalAccessTokenRepositoryInterface;
use App\Services\Notification\NotificationService;
use App\Services\Payment\PaymentChain\IDPayChain\Handlers\Pay\AmountValidationHandler;
use App\Services\Payment\PaymentChain\IDPayChain\Handlers\Pay\BalanceSettingHandler;
use App\Services\Payment\PaymentChain\IDPayChain\Handlers\Pay\BankAccountValidationHandler;
use App\Services\Payment\PaymentChain\IDPayChain\Handlers\Pay\UserExistenceHandler;
use App\Services\Payment\PaymentChain\IDPayChain\Handlers\Verify\BankAccountVerificationHandler;
use App\Services\Payment\PaymentChain\IDPayChain\Handlers\Verify\PaymentExistanceHandler;
use App\Services\Payment\PaymentService;
use App\Services\Payment\Requests\PayRequest as PaymentServicePayRequest;
use App\Services\Payment\Requests\VerifyRequest;
use App\Traits\API\NotificationTrait;
use App\Traits\KYC\PrepareDataForAlertNotification;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use NotificationTrait, PrepareDataForAlertNotification, BalanceLastSetting;

    public function __construct(protected PaymentRepositoryInterface             $paymentRepository,
                                protected PersonalAccessTokenRepositoryInterface $personalAccessTokenRepository,
    )
    {
    }

    public function pay(PayRequest $request)
    {
        $validated_data = $request->validated();

        # Convering amount from IRT to IRR
        $amount = $validated_data['amount'] * 10;

        $data = [
            'user' => $this->personalAccessTokenRepository->findUserByToken($validated_data['token']),
            'balance_setting' => $this->getLastIRRSetting(),
            'amount' => $amount,
            'bank_account' => $validated_data['bank_account'],
        ];

        $userExistenceHandler = new UserExistenceHandler();
        $balanceSettingHandler = new BalanceSettingHandler();
        $amountValidationHandler = new AmountValidationHandler();
        $bankAccountValidationHandler = new BankAccountValidationHandler();

        try {

            # Handling all the condition that we have to check before redirecting user to pay
            $userExistenceHandler->setNext($balanceSettingHandler);
            $balanceSettingHandler->setNext($amountValidationHandler);
            $amountValidationHandler->setNext($bankAccountValidationHandler);

            $userExistenceHandler->handle($data);
            $bankAccount = $bankAccountValidationHandler->handle($data);

            # Sending user to gateway to pay
            $payRequest = new PaymentServicePayRequest((int)$data['amount'], $bankAccount, $data['user']);
            (new PaymentService($payRequest, PaymentService::IDPAY))->sendToGateway();

        } catch (\Exception $e) {
            return redirect()->route('payment.callback.form')->with('failed', $e->getMessage());
        }
    }

    public function verify(Request $request)
    {
        $data = [
            'id' => $request?->id,
            'card_no' => $request?->card_no,
            'order_id' => $request?->order_id,
        ];

        $bankAccountVerificationHandler = new BankAccountVerificationHandler();
        $paymentExistanceHandler = new PaymentExistanceHandler();

        try {
            # Check all the condition that need to be passed for verification
            $paymentExistanceHandler->setNext($bankAccountVerificationHandler);
            $paymentExistanceHandler->handle($data);

            # Verifing user payment
            $payment = $this->paymentRepository->findBy(['factor_number' => $data['order_id']], null, 'first');

            $verifyRequest = new VerifyRequest($payment, $data['order_id'], $data['card_no'], $data['id']);
            $verificationData = (new PaymentService($verifyRequest, PaymentService::IDPAY))->verify();

        } catch (\Exception $e) {
            return redirect()->route('payment.callback.form')->with('failed', $e->getMessage());
        }

        /* alert notification */
        $notification_alert_data = $this->prepareDataForSendAlertNotification($verificationData['user_id'], null, 'واریز تومانی شما به مبلغ ' . number_format((int)$verificationData['amount'] / 10) . ' با موفقیت انجام شد', 'واریز تومانی');
        $notification_service = new NotificationService('alert', $notification_alert_data);
        $notification_service->perform();

        return redirect()->route('payment.callback.form')->with('success', 'پرداخت با موفقیت انجام شد');
    }

    public function showRedirectForm(Request $request)
    {
        return view('payment.callback');
    }
}