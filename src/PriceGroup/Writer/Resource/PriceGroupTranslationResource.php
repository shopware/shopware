<?php declare(strict_types=1);

namespace Shopware\PriceGroup\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class PriceGroupTranslationResource extends Resource
{
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('price_group_translation');

        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields['priceGroup'] = new ReferenceField('priceGroupUuid', 'uuid', \Shopware\PriceGroup\Writer\Resource\PriceGroupResource::class);
        $this->primaryKeyFields['priceGroupUuid'] = (new FkField('price_group_uuid', \Shopware\PriceGroup\Writer\Resource\PriceGroupResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\PriceGroup\Writer\Resource\PriceGroupResource::class,
            \Shopware\Shop\Writer\Resource\ShopResource::class,
            \Shopware\PriceGroup\Writer\Resource\PriceGroupTranslationResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\PriceGroup\Event\PriceGroupTranslationWrittenEvent
    {
        $event = new \Shopware\PriceGroup\Event\PriceGroupTranslationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\PriceGroup\Writer\Resource\PriceGroupResource::class])) {
            $event->addEvent(\Shopware\PriceGroup\Writer\Resource\PriceGroupResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\PriceGroup\Writer\Resource\PriceGroupTranslationResource::class])) {
            $event->addEvent(\Shopware\PriceGroup\Writer\Resource\PriceGroupTranslationResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
