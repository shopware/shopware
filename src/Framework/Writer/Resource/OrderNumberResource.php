<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class OrderNumberResource extends Resource
{
    protected const NUMBER_FIELD = 'number';
    protected const NAME_FIELD = 'name';
    protected const DESC_FIELD = 'desc';

    public function __construct()
    {
        parent::__construct('s_order_number');

        $this->fields[self::NUMBER_FIELD] = (new IntField('number'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::DESC_FIELD] = (new StringField('desc'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\OrderNumberResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Framework\Event\OrderNumberWrittenEvent
    {
        $event = new \Shopware\Framework\Event\OrderNumberWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\OrderNumberResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\OrderNumberResource::createWrittenEvent($updates));
        }

        return $event;
    }
}
