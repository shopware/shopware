<?php declare(strict_types=1);

namespace Shopware\Area\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class AreaTranslationWriteResource extends WriteResource
{
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('area_translation');

        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields['area'] = new ReferenceField('areaUuid', 'uuid', \Shopware\Area\Writer\Resource\AreaWriteResource::class);
        $this->primaryKeyFields['areaUuid'] = (new FkField('area_uuid', \Shopware\Area\Writer\Resource\AreaWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Area\Writer\Resource\AreaWriteResource::class,
            \Shopware\Shop\Writer\Resource\ShopWriteResource::class,
            \Shopware\Area\Writer\Resource\AreaTranslationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Area\Event\AreaTranslationWrittenEvent
    {
        $event = new \Shopware\Area\Event\AreaTranslationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Area\Writer\Resource\AreaWriteResource::class])) {
            $event->addEvent(\Shopware\Area\Writer\Resource\AreaWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopWriteResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Area\Writer\Resource\AreaTranslationWriteResource::class])) {
            $event->addEvent(\Shopware\Area\Writer\Resource\AreaTranslationWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
