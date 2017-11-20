<?php declare(strict_types=1);

namespace Shopware\Payment\Event\PaymentMethod;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Payment\Collection\PaymentMethodBasicCollection;

class PaymentMethodBasicLoadedEvent extends NestedEvent
{
    const NAME = 'payment_method.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var PaymentMethodBasicCollection
     */
    protected $paymentMethods;

    public function __construct(PaymentMethodBasicCollection $paymentMethods, TranslationContext $context)
    {
        $this->context = $context;
        $this->paymentMethods = $paymentMethods;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getPaymentMethods(): PaymentMethodBasicCollection
    {
        return $this->paymentMethods;
    }
}
