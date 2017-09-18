<?php declare(strict_types=1);

namespace Shopware\PaymentMethod\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\PaymentMethod\Struct\PaymentMethodBasicCollection;

class PaymentMethodBasicLoadedEvent extends NestedEvent
{
    const NAME = 'paymentMethod.basic.loaded';

    /**
     * @var PaymentMethodBasicCollection
     */
    protected $paymentMethods;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(PaymentMethodBasicCollection $paymentMethods, TranslationContext $context)
    {
        $this->paymentMethods = $paymentMethods;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getPaymentMethods(): PaymentMethodBasicCollection
    {
        return $this->paymentMethods;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
        ]);
    }
}
