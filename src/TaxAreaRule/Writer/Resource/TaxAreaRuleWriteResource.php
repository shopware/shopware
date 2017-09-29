<?php declare(strict_types=1);

namespace Shopware\TaxAreaRule\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class TaxAreaRuleWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const TAX_RATE_FIELD = 'taxRate';
    protected const ACTIVE_FIELD = 'active';
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('tax_area_rule');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::TAX_RATE_FIELD] = (new FloatField('tax_rate'))->setFlags(new Required());
        $this->fields[self::ACTIVE_FIELD] = new BoolField('active');
        $this->fields['area'] = new ReferenceField('areaUuid', 'uuid', \Shopware\Area\Writer\Resource\AreaWriteResource::class);
        $this->fields['areaUuid'] = (new FkField('area_uuid', \Shopware\Area\Writer\Resource\AreaWriteResource::class, 'uuid'));
        $this->fields['areaCountry'] = new ReferenceField('areaCountryUuid', 'uuid', \Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource::class);
        $this->fields['areaCountryUuid'] = (new FkField('area_country_uuid', \Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource::class, 'uuid'));
        $this->fields['areaCountryState'] = new ReferenceField('areaCountryStateUuid', 'uuid', \Shopware\AreaCountryState\Writer\Resource\AreaCountryStateWriteResource::class);
        $this->fields['areaCountryStateUuid'] = (new FkField('area_country_state_uuid', \Shopware\AreaCountryState\Writer\Resource\AreaCountryStateWriteResource::class, 'uuid'));
        $this->fields['tax'] = new ReferenceField('taxUuid', 'uuid', \Shopware\Tax\Writer\Resource\TaxWriteResource::class);
        $this->fields['taxUuid'] = (new FkField('tax_uuid', \Shopware\Tax\Writer\Resource\TaxWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['customerGroup'] = new ReferenceField('customerGroupUuid', 'uuid', \Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource::class);
        $this->fields['customerGroupUuid'] = (new FkField('customer_group_uuid', \Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Area\Writer\Resource\AreaWriteResource::class,
            \Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource::class,
            \Shopware\AreaCountryState\Writer\Resource\AreaCountryStateWriteResource::class,
            \Shopware\Tax\Writer\Resource\TaxWriteResource::class,
            \Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource::class,
            \Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleWriteResource::class,
            \Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleTranslationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\TaxAreaRule\Event\TaxAreaRuleWrittenEvent
    {
        $event = new \Shopware\TaxAreaRule\Event\TaxAreaRuleWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Area\Writer\Resource\AreaWriteResource::class])) {
            $event->addEvent(\Shopware\Area\Writer\Resource\AreaWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource::class])) {
            $event->addEvent(\Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\AreaCountryState\Writer\Resource\AreaCountryStateWriteResource::class])) {
            $event->addEvent(\Shopware\AreaCountryState\Writer\Resource\AreaCountryStateWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Tax\Writer\Resource\TaxWriteResource::class])) {
            $event->addEvent(\Shopware\Tax\Writer\Resource\TaxWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource::class])) {
            $event->addEvent(\Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleWriteResource::class])) {
            $event->addEvent(\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleTranslationWriteResource::class])) {
            $event->addEvent(\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleTranslationWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
