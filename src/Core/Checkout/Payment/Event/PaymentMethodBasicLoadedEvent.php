<?php declare(strict_types=1);

namespace Shopware\Checkout\Payment\Event;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Checkout\Payment\Collection\PaymentMethodBasicCollection;
use Shopware\Framework\Event\NestedEvent;

class PaymentMethodBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'payment_method.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var PaymentMethodBasicCollection
     */
    protected $paymentMethods;

    public function __construct(PaymentMethodBasicCollection $paymentMethods, ApplicationContext $context)
    {
        $this->context = $context;
        $this->paymentMethods = $paymentMethods;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getPaymentMethods(): PaymentMethodBasicCollection
    {
        return $this->paymentMethods;
    }
}
