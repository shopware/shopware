<?php declare(strict_types=1);

namespace Shopware\AreaCountryState\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class AreaCountryStateTranslationWriteResource extends WriteResource
{
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('area_country_state_translation');

        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields['areaCountryState'] = new ReferenceField('areaCountryStateUuid', 'uuid', \Shopware\AreaCountryState\Writer\Resource\AreaCountryStateWriteResource::class);
        $this->primaryKeyFields['areaCountryStateUuid'] = (new FkField('area_country_state_uuid', \Shopware\AreaCountryState\Writer\Resource\AreaCountryStateWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\AreaCountryState\Writer\Resource\AreaCountryStateWriteResource::class,
            \Shopware\Shop\Writer\Resource\ShopWriteResource::class,
            \Shopware\AreaCountryState\Writer\Resource\AreaCountryStateTranslationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\AreaCountryState\Event\AreaCountryStateTranslationWrittenEvent
    {
        $event = new \Shopware\AreaCountryState\Event\AreaCountryStateTranslationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\AreaCountryState\Writer\Resource\AreaCountryStateWriteResource::class])) {
            $event->addEvent(\Shopware\AreaCountryState\Writer\Resource\AreaCountryStateWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopWriteResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\AreaCountryState\Writer\Resource\AreaCountryStateTranslationWriteResource::class])) {
            $event->addEvent(\Shopware\AreaCountryState\Writer\Resource\AreaCountryStateTranslationWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
