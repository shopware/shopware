<?php declare(strict_types=1);

namespace Shopware\Unit\Writer\Resource;

use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class UnitTranslationResource extends Resource
{
    protected const SHORT_CODE_FIELD = 'shortCode';
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('unit_translation');

        $this->fields[self::SHORT_CODE_FIELD] = (new StringField('short_code'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields['unit'] = new ReferenceField('unitUuid', 'uuid', \Shopware\Unit\Writer\Resource\UnitResource::class);
        $this->primaryKeyFields['unitUuid'] = (new FkField('unit_uuid', \Shopware\Unit\Writer\Resource\UnitResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Unit\Writer\Resource\UnitResource::class,
            \Shopware\Shop\Writer\Resource\ShopResource::class,
            \Shopware\Unit\Writer\Resource\UnitTranslationResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Unit\Event\UnitTranslationWrittenEvent
    {
        $event = new \Shopware\Unit\Event\UnitTranslationWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Unit\Writer\Resource\UnitResource::class])) {
            $event->addEvent(\Shopware\Unit\Writer\Resource\UnitResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Unit\Writer\Resource\UnitTranslationResource::class])) {
            $event->addEvent(\Shopware\Unit\Writer\Resource\UnitTranslationResource::createWrittenEvent($updates));
        }

        return $event;
    }
}
