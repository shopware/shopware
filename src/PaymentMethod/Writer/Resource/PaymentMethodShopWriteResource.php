<?php declare(strict_types=1);

namespace Shopware\PaymentMethod\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\PaymentMethod\Event\PaymentMethodShopWrittenEvent;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

class PaymentMethodShopWriteResource extends WriteResource
{
    public function __construct()
    {
        parent::__construct('payment_method_shop');

        $this->fields['paymentMethod'] = new ReferenceField('paymentMethodUuid', 'uuid', PaymentMethodWriteResource::class);
        $this->fields['paymentMethodUuid'] = (new FkField('payment_method_uuid', PaymentMethodWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['shop'] = new ReferenceField('shopUuid', 'uuid', ShopWriteResource::class);
        $this->fields['shopUuid'] = (new FkField('shop_uuid', ShopWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            PaymentMethodWriteResource::class,
            ShopWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): PaymentMethodShopWrittenEvent
    {
        $event = new PaymentMethodShopWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        /**
         * @var WriteResource
         * @var string[]      $identifiers
         */
        foreach ($updates as $class => $identifiers) {
            if (!array_key_exists($class, $updates) || count($updates[$class]) === 0) {
                continue;
            }

            $event->addEvent($class::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
