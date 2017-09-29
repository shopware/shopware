<?php declare(strict_types=1);

namespace Shopware\ShippingMethod\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class ShippingMethodPaymentMethodWriteResource extends WriteResource
{
    public function __construct()
    {
        parent::__construct('shipping_method_payment_method');

        $this->fields['shippingMethod'] = new ReferenceField('shippingMethodUuid', 'uuid', \Shopware\ShippingMethod\Writer\Resource\ShippingMethodWriteResource::class);
        $this->fields['shippingMethodUuid'] = (new FkField('shipping_method_uuid', \Shopware\ShippingMethod\Writer\Resource\ShippingMethodWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['paymentMethod'] = new ReferenceField('paymentMethodUuid', 'uuid', \Shopware\PaymentMethod\Writer\Resource\PaymentMethodWriteResource::class);
        $this->fields['paymentMethodUuid'] = (new FkField('payment_method_uuid', \Shopware\PaymentMethod\Writer\Resource\PaymentMethodWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\ShippingMethod\Writer\Resource\ShippingMethodWriteResource::class,
            \Shopware\PaymentMethod\Writer\Resource\PaymentMethodWriteResource::class,
            \Shopware\ShippingMethod\Writer\Resource\ShippingMethodPaymentMethodWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\ShippingMethod\Event\ShippingMethodPaymentMethodWrittenEvent
    {
        $event = new \Shopware\ShippingMethod\Event\ShippingMethodPaymentMethodWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\ShippingMethod\Writer\Resource\ShippingMethodWriteResource::class])) {
            $event->addEvent(\Shopware\ShippingMethod\Writer\Resource\ShippingMethodWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\PaymentMethod\Writer\Resource\PaymentMethodWriteResource::class])) {
            $event->addEvent(\Shopware\PaymentMethod\Writer\Resource\PaymentMethodWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\ShippingMethod\Writer\Resource\ShippingMethodPaymentMethodWriteResource::class])) {
            $event->addEvent(\Shopware\ShippingMethod\Writer\Resource\ShippingMethodPaymentMethodWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
