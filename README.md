# payment-service
Laravel implemented payment service 

This service is actually the payment service that I use for my Laravel project.
All you need to do is copy the ``payment`` folder and paste it in App directory in your Laravel project.

## How does it work?

If you wanna see the example ensure that you checked the ``example`` directory in this repository, It gives you an example of usage in ``PaymentController``.
<br>
The pattern we are using is [Strategy](https://refactoring.guru/design-patterns/strategy) that is one of the behavioral patterns. <br>
First we need to define our interfaces to know what actually we will face in this service, We all know that every single provider has a ``pay`` method becuase it needs to be paied but some of them has ``verify`` method because all the payment are not verifiable, Like offline transactions. <br>
So we have to define two different interfaces called ``Payable`` and ``Verifiable`` (These interfaces are in ``Payment/Contracts``).

#### Payable interface
```php
namespace App\Services\Payment\Contracts;

use App\Services\Payment\Requests\PayRequest;

interface PayableInterface
{
    public function pay(PayRequest $dataRequest);
}
```

#### Verifiable interface
```php
namespace App\Services\Payment\Contracts;

use App\Services\Payment\Requests\VerifyRequest;

interface VerifiableInterface
{
    public function verify(VerifyRequest $verifyRequest);
}
```
So every provider that wants to be added to this service has to implement at least the Payable interface, Like:
```php
namespace App\Services\Payment\Providers;

use App\Services\Payment\Contracts\PayableInterface;
use App\Services\Payment\Contracts\VerifiableInterface;
use App\Services\Payment\Requests\PayRequest;
use App\Services\Payment\Requests\VerifyRequest;

class VandarProvider implements PayableInterface, VerifiableInterface
{

    public function pay(PayRequest $dataRequest)
    {
        // TODO: Implement pay() method.
    }

    public function verify(VerifyRequest $verifyRequest)
    {
        // TODO: Implement verify() method.
    }
}
```

### Let's find out How payment service class works

Let me show you the actual code then we can discuss about it.

```php
class PaymentService
{
    public const IDPAY = 'IDPayProvider';

    public const Vandar = 'VandarProvider';

    public function __construct(protected PayRequest|VerifyRequest $dataRequest, protected string $gateway = self::IDPAY)
    {
    }

    private function findGateway()
    {
        $namespace = "App\Services\Payment\Providers\\" . $this->gateway;

        if (!class_exists($namespace)) {
            throw new GatewayDoesNotExistsException('درگاه مورد نظر وجود ندارد');
        }

        return (new $namespace);
    }

    public function sendToGateway()
    {
        return $this->findGateway()->pay($this->dataRequest);
    }

    public function verify()
    {
        return $this->findGateway()->verify($this->dataRequest);
    }
}

```

If you want to use Payment you have to start from ``PaymentService`` class. In This class we defined our different provider names as constant, Like ``IDPAY`` or ``Vandar``. <br>
In the construct of class we have two parameters, First one if ``$dataRequest`` and the second one is the gatewat type. <br>
#### What is ``$dataRequest`` anyway?
In the example of this class you will see:
```php
use App\Services\Payment\Requests\PayRequest as PaymentServicePayRequest;

$payRequest = new PaymentServicePayRequest((int)$data['amount'], $bankAccount, $data['user']);
(new PaymentService($payRequest, PaymentService::IDPAY))->sendToGateway();
```
If you noticed the constructor the first paramter is type of ``PayRequest`` or ``VerifyRequest``, These two class has the necessary data that we need to use in every single provider, Like ``amount``, the actual ``user``, ``user's bank account``, etc to pay or verify!<br>

<hr>

The ``findGateway`` method in the ``PaymentService`` class returns the object of the provider that you passed in the second parameter of the constructor. <br>
As we defined our interfaces we are sure that both of them has at least the ``Pay`` method, So in the ``sendToGateway`` method we call the ``pay`` method on the object that ``sendToGateway`` returns to us and we pass the ``$dataReqeust`` as well, Ultimately the user will be redirected to payment page. <br>

### What happens when the user come back from payment page?
In this case users get back to ``verify`` method from payment page to the route that you given as ``callback`` route. Let's find out ``verify`` <br>

```php
use App\Services\Payment\Requests\VerifyRequest;

$verifyRequest = new VerifyRequest($payment, $data['order_id'], $data['card_no'], $data['id']);
$verificationData = (new PaymentService($verifyRequest, PaymentService::IDPAY))->verify();
```
In the verify method we just need to do the same stuff that we've done in the above section, But we need to use ``VerifyRequest`` class instedOf ``PayReuqest`` because in this case we want to verify the payment. <br>
For verifying the payment we need to pass the data to ``VerifyRequest`` and pass it to ``PaymentService`` and finially call the ``Verify`` method of the ``PaymentService`` class.

## Example

```php
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

            # Verifying user payment
            $payment = $this->paymentRepository->findBy(['factor_number' => $data['order_id']], null, 'first');

            $verifyRequest = new VerifyRequest($payment, $data['order_id'], $data['card_no'], $data['id']);
            $verificationData = (new PaymentService($verifyRequest, PaymentService::IDPAY))->verify();

        } catch (\Exception $e) {
            return redirect()->route('payment.callback.form')->with('failed', $e->getMessage());
        }

        return redirect()->route('payment.callback.form')->with('success', 'پرداخت با موفقیت انجام شد');
    }

    public function showRedirectForm(Request $request)
    {
        return view('payment.callback');
    }
}
```

The chain of responsibility explanation will be added soon.