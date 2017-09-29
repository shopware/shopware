<?php declare(strict_types=1);

namespace Shopware\Area\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class AreaWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const ACTIVE_FIELD = 'active';
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('area');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::ACTIVE_FIELD] = new BoolField('active');
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\Area\Writer\Resource\AreaTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['countries'] = new SubresourceField(\Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource::class);
        $this->fields['taxAreaRules'] = new SubresourceField(\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Area\Writer\Resource\AreaWriteResource::class,
            \Shopware\Area\Writer\Resource\AreaTranslationWriteResource::class,
            \Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource::class,
            \Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Area\Event\AreaWrittenEvent
    {
        $event = new \Shopware\Area\Event\AreaWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Area\Writer\Resource\AreaWriteResource::class])) {
            $event->addEvent(\Shopware\Area\Writer\Resource\AreaWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Area\Writer\Resource\AreaTranslationWriteResource::class])) {
            $event->addEvent(\Shopware\Area\Writer\Resource\AreaTranslationWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource::class])) {
            $event->addEvent(\Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleWriteResource::class])) {
            $event->addEvent(\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
