<?php declare(strict_types=1);

namespace Shopware\AreaCountryState\Writer\Resource;

use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class AreaCountryStateResource extends Resource
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
        $this->fields['areaCountry'] = new ReferenceField('areaCountryUuid', 'uuid', \Shopware\AreaCountry\Writer\Resource\AreaCountryResource::class);
        $this->fields['areaCountryUuid'] = (new FkField('area_country_uuid', \Shopware\AreaCountry\Writer\Resource\AreaCountryResource::class, 'uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\AreaCountryState\Writer\Resource\AreaCountryStateTranslationResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['customerAddresses'] = new SubresourceField(\Shopware\CustomerAddress\Writer\Resource\CustomerAddressResource::class);
        $this->fields['orderAddresses'] = new SubresourceField(\Shopware\OrderAddress\Writer\Resource\OrderAddressResource::class);
        $this->fields['taxAreaRules'] = new SubresourceField(\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\AreaCountry\Writer\Resource\AreaCountryResource::class,
            \Shopware\AreaCountryState\Writer\Resource\AreaCountryStateResource::class,
            \Shopware\AreaCountryState\Writer\Resource\AreaCountryStateTranslationResource::class,
            \Shopware\CustomerAddress\Writer\Resource\CustomerAddressResource::class,
            \Shopware\OrderAddress\Writer\Resource\OrderAddressResource::class,
            \Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\AreaCountryState\Event\AreaCountryStateWrittenEvent
    {
        $event = new \Shopware\AreaCountryState\Event\AreaCountryStateWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\AreaCountry\Writer\Resource\AreaCountryResource::class])) {
            $event->addEvent(\Shopware\AreaCountry\Writer\Resource\AreaCountryResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\AreaCountryState\Writer\Resource\AreaCountryStateResource::class])) {
            $event->addEvent(\Shopware\AreaCountryState\Writer\Resource\AreaCountryStateResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\AreaCountryState\Writer\Resource\AreaCountryStateTranslationResource::class])) {
            $event->addEvent(\Shopware\AreaCountryState\Writer\Resource\AreaCountryStateTranslationResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\CustomerAddress\Writer\Resource\CustomerAddressResource::class])) {
            $event->addEvent(\Shopware\CustomerAddress\Writer\Resource\CustomerAddressResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\OrderAddress\Writer\Resource\OrderAddressResource::class])) {
            $event->addEvent(\Shopware\OrderAddress\Writer\Resource\OrderAddressResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleResource::class])) {
            $event->addEvent(\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleResource::createWrittenEvent($updates));
        }

        return $event;
    }
}
