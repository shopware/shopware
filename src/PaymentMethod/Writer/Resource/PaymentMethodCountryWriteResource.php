<?php declare(strict_types=1);

namespace Shopware\PaymentMethod\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class PaymentMethodCountryWriteResource extends WriteResource
{
    public function __construct()
    {
        parent::__construct('payment_method_country');

        $this->fields['paymentMethod'] = new ReferenceField('paymentMethodUuid', 'uuid', \Shopware\PaymentMethod\Writer\Resource\PaymentMethodWriteResource::class);
        $this->fields['paymentMethodUuid'] = (new FkField('payment_method_uuid', \Shopware\PaymentMethod\Writer\Resource\PaymentMethodWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['areaCountry'] = new ReferenceField('areaCountryUuid', 'uuid', \Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource::class);
        $this->fields['areaCountryUuid'] = (new FkField('area_country_uuid', \Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\PaymentMethod\Writer\Resource\PaymentMethodWriteResource::class,
            \Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource::class,
            \Shopware\PaymentMethod\Writer\Resource\PaymentMethodCountryWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\PaymentMethod\Event\PaymentMethodCountryWrittenEvent
    {
        $event = new \Shopware\PaymentMethod\Event\PaymentMethodCountryWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\PaymentMethod\Writer\Resource\PaymentMethodWriteResource::class])) {
            $event->addEvent(\Shopware\PaymentMethod\Writer\Resource\PaymentMethodWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource::class])) {
            $event->addEvent(\Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\PaymentMethod\Writer\Resource\PaymentMethodCountryWriteResource::class])) {
            $event->addEvent(\Shopware\PaymentMethod\Writer\Resource\PaymentMethodCountryWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
