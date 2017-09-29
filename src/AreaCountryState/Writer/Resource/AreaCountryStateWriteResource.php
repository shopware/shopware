<?php declare(strict_types=1);

namespace Shopware\AreaCountryState\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class AreaCountryStateWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const SHORT_CODE_FIELD = 'shortCode';
    protected const POSITION_FIELD = 'position';
    protected const ACTIVE_FIELD = 'active';
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('area_country_state');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::SHORT_CODE_FIELD] = (new StringField('short_code'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = new IntField('position');
        $this->fields[self::ACTIVE_FIELD] = new BoolField('active');
        $this->fields['areaCountry'] = new ReferenceField('areaCountryUuid', 'uuid', \Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource::class);
        $this->fields['areaCountryUuid'] = (new FkField('area_country_uuid', \Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\AreaCountryState\Writer\Resource\AreaCountryStateTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['customerAddresses'] = new SubresourceField(\Shopware\CustomerAddress\Writer\Resource\CustomerAddressWriteResource::class);
        $this->fields['orderAddresses'] = new SubresourceField(\Shopware\OrderAddress\Writer\Resource\OrderAddressWriteResource::class);
        $this->fields['taxAreaRules'] = new SubresourceField(\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource::class,
            \Shopware\AreaCountryState\Writer\Resource\AreaCountryStateWriteResource::class,
            \Shopware\AreaCountryState\Writer\Resource\AreaCountryStateTranslationWriteResource::class,
            \Shopware\CustomerAddress\Writer\Resource\CustomerAddressWriteResource::class,
            \Shopware\OrderAddress\Writer\Resource\OrderAddressWriteResource::class,
            \Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\AreaCountryState\Event\AreaCountryStateWrittenEvent
    {
        $event = new \Shopware\AreaCountryState\Event\AreaCountryStateWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource::class])) {
            $event->addEvent(\Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\AreaCountryState\Writer\Resource\AreaCountryStateWriteResource::class])) {
            $event->addEvent(\Shopware\AreaCountryState\Writer\Resource\AreaCountryStateWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\AreaCountryState\Writer\Resource\AreaCountryStateTranslationWriteResource::class])) {
            $event->addEvent(\Shopware\AreaCountryState\Writer\Resource\AreaCountryStateTranslationWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\CustomerAddress\Writer\Resource\CustomerAddressWriteResource::class])) {
            $event->addEvent(\Shopware\CustomerAddress\Writer\Resource\CustomerAddressWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\OrderAddress\Writer\Resource\OrderAddressWriteResource::class])) {
            $event->addEvent(\Shopware\OrderAddress\Writer\Resource\OrderAddressWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleWriteResource::class])) {
            $event->addEvent(\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
