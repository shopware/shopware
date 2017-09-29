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

class StatisticVisitorWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const SHOP_ID_FIELD = 'shopId';
    protected const PAGE_IMPRESSIONS_FIELD = 'pageImpressions';
    protected const UNIQUE_VISITS_FIELD = 'uniqueVisits';
    protected const DEVICE_TYPE_FIELD = 'deviceType';

    public function __construct()
    {
        parent::__construct('statistic_visitor');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::SHOP_ID_FIELD] = (new IntField('shop_id'))->setFlags(new Required());
        $this->fields[self::PAGE_IMPRESSIONS_FIELD] = new IntField('page_impressions');
        $this->fields[self::UNIQUE_VISITS_FIELD] = new IntField('unique_visits');
        $this->fields[self::DEVICE_TYPE_FIELD] = new StringField('device_type');
        $this->fields['shop'] = new ReferenceField('shopUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class);
        $this->fields['shopUuid'] = (new FkField('shop_uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Shop\Writer\Resource\ShopWriteResource::class,
            \Shopware\Framework\Write\Resource\StatisticVisitorWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\StatisticVisitorWrittenEvent
    {
        $event = new \Shopware\Framework\Event\StatisticVisitorWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopWriteResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\StatisticVisitorWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\StatisticVisitorWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
