<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class FilterValueTranslationResource extends Resource
{
    protected const VALUE_FIELD = 'value';

    public function __construct()
    {
        parent::__construct('filter_value_translation');

        $this->fields[self::VALUE_FIELD] = (new LongTextField('value'))->setFlags(new Required());
        $this->fields['filterValue'] = new ReferenceField('filterValueUuid', 'uuid', \Shopware\Framework\Write\Resource\FilterValueResource::class);
        $this->primaryKeyFields['filterValueUuid'] = (new FkField('filter_value_uuid', \Shopware\Framework\Write\Resource\FilterValueResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\FilterValueResource::class,
            \Shopware\Shop\Writer\Resource\ShopResource::class,
            \Shopware\Framework\Write\Resource\FilterValueTranslationResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\FilterValueTranslationWrittenEvent
    {
        $event = new \Shopware\Framework\Event\FilterValueTranslationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterValueResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterValueResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterValueTranslationResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterValueTranslationResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
