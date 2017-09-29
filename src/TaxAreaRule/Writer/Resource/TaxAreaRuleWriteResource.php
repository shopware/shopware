<?php declare(strict_types=1);

namespace Shopware\TaxAreaRule\Writer\Resource;

use Shopware\Area\Writer\Resource\AreaWriteResource;
use Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource;
use Shopware\AreaCountryState\Writer\Resource\AreaCountryStateWriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\Shop\Writer\Resource\ShopWriteResource;
use Shopware\Tax\Writer\Resource\TaxWriteResource;
use Shopware\TaxAreaRule\Event\TaxAreaRuleWrittenEvent;

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
        $this->fields['area'] = new ReferenceField('areaUuid', 'uuid', AreaWriteResource::class);
        $this->fields['areaUuid'] = (new FkField('area_uuid', AreaWriteResource::class, 'uuid'));
        $this->fields['areaCountry'] = new ReferenceField('areaCountryUuid', 'uuid', AreaCountryWriteResource::class);
        $this->fields['areaCountryUuid'] = (new FkField('area_country_uuid', AreaCountryWriteResource::class, 'uuid'));
        $this->fields['areaCountryState'] = new ReferenceField('areaCountryStateUuid', 'uuid', AreaCountryStateWriteResource::class);
        $this->fields['areaCountryStateUuid'] = (new FkField('area_country_state_uuid', AreaCountryStateWriteResource::class, 'uuid'));
        $this->fields['tax'] = new ReferenceField('taxUuid', 'uuid', TaxWriteResource::class);
        $this->fields['taxUuid'] = (new FkField('tax_uuid', TaxWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['customerGroup'] = new ReferenceField('customerGroupUuid', 'uuid', CustomerGroupWriteResource::class);
        $this->fields['customerGroupUuid'] = (new FkField('customer_group_uuid', CustomerGroupWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(TaxAreaRuleTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            AreaWriteResource::class,
            AreaCountryWriteResource::class,
            AreaCountryStateWriteResource::class,
            TaxWriteResource::class,
            CustomerGroupWriteResource::class,
            self::class,
            TaxAreaRuleTranslationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): TaxAreaRuleWrittenEvent
    {
        $event = new TaxAreaRuleWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[AreaWriteResource::class])) {
            $event->addEvent(AreaWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[AreaCountryWriteResource::class])) {
            $event->addEvent(AreaCountryWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[AreaCountryStateWriteResource::class])) {
            $event->addEvent(AreaCountryStateWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[TaxWriteResource::class])) {
            $event->addEvent(TaxWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[CustomerGroupWriteResource::class])) {
            $event->addEvent(CustomerGroupWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[TaxAreaRuleTranslationWriteResource::class])) {
            $event->addEvent(TaxAreaRuleTranslationWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
