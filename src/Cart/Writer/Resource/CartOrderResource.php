<?php declare(strict_types=1);

namespace Shopware\Cart\Writer\Resource;

use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class CartOrderResource extends Resource
{
    protected const TOKEN_FIELD = 'token';
    protected const NAME_FIELD = 'name';
    protected const CONTENT_FIELD = 'content';
    protected const ORDER_TIME_FIELD = 'orderTime';

    public function __construct()
    {
        parent::__construct('s_cart_order');

        $this->fields[self::TOKEN_FIELD] = (new StringField('token'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::CONTENT_FIELD] = (new LongTextField('content'))->setFlags(new Required());
        $this->fields[self::ORDER_TIME_FIELD] = (new DateField('order_time'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Cart\Writer\Resource\CartOrderResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Cart\Event\CartOrderWrittenEvent
    {
        $event = new \Shopware\Cart\Event\CartOrderWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Cart\Writer\Resource\CartOrderResource::class])) {
            $event->addEvent(\Shopware\Cart\Writer\Resource\CartOrderResource::createWrittenEvent($updates));
        }

        return $event;
    }
}
