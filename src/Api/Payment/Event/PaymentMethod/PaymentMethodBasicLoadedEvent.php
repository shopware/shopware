<?php declare(strict_types=1);

namespace Shopware\Api\Payment\Event\PaymentMethod;

use Shopware\Api\Payment\Collection\PaymentMethodBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class PaymentMethodBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'payment_method.basic.loaded';

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
