<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class FilterOptionTranslationWriteResource extends WriteResource
{
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('filter_option_translation');

        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields['filterOption'] = new ReferenceField('filterOptionUuid', 'uuid', \Shopware\Framework\Write\Resource\FilterOptionWriteResource::class);
        $this->primaryKeyFields['filterOptionUuid'] = (new FkField('filter_option_uuid', \Shopware\Framework\Write\Resource\FilterOptionWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\FilterOptionWriteResource::class,
            \Shopware\Shop\Writer\Resource\ShopWriteResource::class,
            \Shopware\Framework\Write\Resource\FilterOptionTranslationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\FilterOptionTranslationWrittenEvent
    {
        $event = new \Shopware\Framework\Event\FilterOptionTranslationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterOptionWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterOptionWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopWriteResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterOptionTranslationWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterOptionTranslationWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
