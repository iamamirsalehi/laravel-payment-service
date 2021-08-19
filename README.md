# payment-service
Laravel implemented payment service 

This service is actually the payment service that I use for my Laravel project.
All you need to do is copy the ``payment`` folder and paste it in App directory in your Laravel project.

## How does it work?

If you wanna see the example ensure that you checked the ``example`` directory in this repository, It gives you an example of usage in ``PaymentController``.
<br>

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

If you want to use Payment you have to start from ``PaymentService`` class. <br>
In This class we defined our different provider names as constant, Like ``IDPAY`` or ``Vandar``