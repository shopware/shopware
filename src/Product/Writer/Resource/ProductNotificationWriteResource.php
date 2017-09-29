<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class ProductNotificationWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const ORDER_NUMBER_FIELD = 'orderNumber';
    protected const MAIL_FIELD = 'mail';
    protected const SEND_FIELD = 'send';
    protected const LANGUAGE_FIELD = 'language';
    protected const SHOP_LINK_FIELD = 'shopLink';

    public function __construct()
    {
        parent::__construct('product_notification');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::ORDER_NUMBER_FIELD] = (new StringField('order_number'))->setFlags(new Required());
        $this->fields[self::MAIL_FIELD] = (new StringField('mail'))->setFlags(new Required());
        $this->fields[self::SEND_FIELD] = (new IntField('send'))->setFlags(new Required());
        $this->fields[self::LANGUAGE_FIELD] = (new StringField('language'))->setFlags(new Required());
        $this->fields[self::SHOP_LINK_FIELD] = (new StringField('shop_link'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductNotificationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Product\Event\ProductNotificationWrittenEvent
    {
        $event = new \Shopware\Product\Event\ProductNotificationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductNotificationWriteResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductNotificationWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
