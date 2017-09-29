<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class StatisticSearchWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const TERM_FIELD = 'term';
    protected const RESULT_COUNT_FIELD = 'resultCount';
    protected const SHOP_ID_FIELD = 'shopId';

    public function __construct()
    {
        parent::__construct('statistic_search');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::TERM_FIELD] = (new StringField('term'))->setFlags(new Required());
        $this->fields[self::RESULT_COUNT_FIELD] = (new IntField('result_count'))->setFlags(new Required());
        $this->fields[self::SHOP_ID_FIELD] = new IntField('shop_id');
        $this->fields['shop'] = new ReferenceField('shopUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class);
        $this->fields['shopUuid'] = (new FkField('shop_uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid'));
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Shop\Writer\Resource\ShopWriteResource::class,
            \Shopware\Framework\Write\Resource\StatisticSearchWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\StatisticSearchWrittenEvent
    {
        $event = new \Shopware\Framework\Event\StatisticSearchWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopWriteResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\StatisticSearchWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\StatisticSearchWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
