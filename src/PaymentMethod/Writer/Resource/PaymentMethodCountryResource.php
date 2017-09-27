<?php declare(strict_types=1);

namespace Shopware\PaymentMethod\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class PaymentMethodCountryResource extends Resource
{
    protected const CREATED_AT_FIELD = 'createdAt';
    protected const UPDATED_AT_FIELD = 'updatedAt';

    public function __construct()
    {
        parent::__construct('payment_method_country');

        $this->fields[self::CREATED_AT_FIELD] = new DateField('created_at');
        $this->fields[self::UPDATED_AT_FIELD] = new DateField('updated_at');
        $this->fields['paymentMethod'] = new ReferenceField('paymentMethodUuid', 'uuid', \Shopware\PaymentMethod\Writer\Resource\PaymentMethodResource::class);
        $this->fields['paymentMethodUuid'] = (new FkField('payment_method_uuid', \Shopware\PaymentMethod\Writer\Resource\PaymentMethodResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['areaCountry'] = new ReferenceField('areaCountryUuid', 'uuid', \Shopware\AreaCountry\Writer\Resource\AreaCountryResource::class);
        $this->fields['areaCountryUuid'] = (new FkField('area_country_uuid', \Shopware\AreaCountry\Writer\Resource\AreaCountryResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\PaymentMethod\Writer\Resource\PaymentMethodResource::class,
            \Shopware\AreaCountry\Writer\Resource\AreaCountryResource::class,
            \Shopware\PaymentMethod\Writer\Resource\PaymentMethodCountryResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\PaymentMethod\Event\PaymentMethodCountryWrittenEvent
    {
        $event = new \Shopware\PaymentMethod\Event\PaymentMethodCountryWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\PaymentMethod\Writer\Resource\PaymentMethodResource::class])) {
            $event->addEvent(\Shopware\PaymentMethod\Writer\Resource\PaymentMethodResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\AreaCountry\Writer\Resource\AreaCountryResource::class])) {
            $event->addEvent(\Shopware\AreaCountry\Writer\Resource\AreaCountryResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\PaymentMethod\Writer\Resource\PaymentMethodCountryResource::class])) {
            $event->addEvent(\Shopware\PaymentMethod\Writer\Resource\PaymentMethodCountryResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }

    public function getDefaults(string $type): array
    {
        if (self::FOR_UPDATE === $type) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
            ];
        }

        if (self::FOR_INSERT === $type) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
                self::CREATED_AT_FIELD => new \DateTime(),
            ];
        }

        throw new \InvalidArgumentException('Unable to generate default values, wrong type submitted');
    }
}
