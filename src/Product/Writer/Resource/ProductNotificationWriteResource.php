<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Product\Event\ProductNotificationWrittenEvent;

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
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ProductNotificationWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new ProductNotificationWrittenEvent($uuids, $context, $rawData, $errors);

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
