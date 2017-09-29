<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class FilterValueTranslationWriteResource extends WriteResource
{
    protected const VALUE_FIELD = 'value';

    public function __construct()
    {
        parent::__construct('filter_value_translation');

        $this->fields[self::VALUE_FIELD] = (new LongTextField('value'))->setFlags(new Required());
        $this->fields['filterValue'] = new ReferenceField('filterValueUuid', 'uuid', \Shopware\Framework\Write\Resource\FilterValueWriteResource::class);
        $this->primaryKeyFields['filterValueUuid'] = (new FkField('filter_value_uuid', \Shopware\Framework\Write\Resource\FilterValueWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\FilterValueWriteResource::class,
            \Shopware\Shop\Writer\Resource\ShopWriteResource::class,
            \Shopware\Framework\Write\Resource\FilterValueTranslationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\FilterValueTranslationWrittenEvent
    {
        $event = new \Shopware\Framework\Event\FilterValueTranslationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterValueWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterValueWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopWriteResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterValueTranslationWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterValueTranslationWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
