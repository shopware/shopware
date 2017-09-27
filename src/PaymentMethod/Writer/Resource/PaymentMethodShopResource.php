<?php declare(strict_types=1);

namespace Shopware\PaymentMethod\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class PaymentMethodShopResource extends Resource
{
    protected const CREATED_AT_FIELD = 'createdAt';
    protected const UPDATED_AT_FIELD = 'updatedAt';

    public function __construct()
    {
        parent::__construct('payment_method_shop');

        $this->fields[self::CREATED_AT_FIELD] = new DateField('created_at');
        $this->fields[self::UPDATED_AT_FIELD] = new DateField('updated_at');
        $this->fields['paymentMethod'] = new ReferenceField('paymentMethodUuid', 'uuid', \Shopware\PaymentMethod\Writer\Resource\PaymentMethodResource::class);
        $this->fields['paymentMethodUuid'] = (new FkField('payment_method_uuid', \Shopware\PaymentMethod\Writer\Resource\PaymentMethodResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['shop'] = new ReferenceField('shopUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->fields['shopUuid'] = (new FkField('shop_uuid', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\PaymentMethod\Writer\Resource\PaymentMethodResource::class,
            \Shopware\Shop\Writer\Resource\ShopResource::class,
            \Shopware\PaymentMethod\Writer\Resource\PaymentMethodShopResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\PaymentMethod\Event\PaymentMethodShopWrittenEvent
    {
        $event = new \Shopware\PaymentMethod\Event\PaymentMethodShopWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\PaymentMethod\Writer\Resource\PaymentMethodResource::class])) {
            $event->addEvent(\Shopware\PaymentMethod\Writer\Resource\PaymentMethodResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\PaymentMethod\Writer\Resource\PaymentMethodShopResource::class])) {
            $event->addEvent(\Shopware\PaymentMethod\Writer\Resource\PaymentMethodShopResource::createWrittenEvent($updates, $context));
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
