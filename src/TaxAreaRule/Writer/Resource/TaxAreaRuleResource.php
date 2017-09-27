<?php declare(strict_types=1);

namespace Shopware\TaxAreaRule\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class TaxAreaRuleResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const TAX_RATE_FIELD = 'taxRate';
    protected const ACTIVE_FIELD = 'active';
    protected const CREATED_AT_FIELD = 'createdAt';
    protected const UPDATED_AT_FIELD = 'updatedAt';
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('tax_area_rule');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::TAX_RATE_FIELD] = (new FloatField('tax_rate'))->setFlags(new Required());
        $this->fields[self::ACTIVE_FIELD] = new BoolField('active');
        $this->fields[self::CREATED_AT_FIELD] = new DateField('created_at');
        $this->fields[self::UPDATED_AT_FIELD] = new DateField('updated_at');
        $this->fields['area'] = new ReferenceField('areaUuid', 'uuid', \Shopware\Area\Writer\Resource\AreaResource::class);
        $this->fields['areaUuid'] = (new FkField('area_uuid', \Shopware\Area\Writer\Resource\AreaResource::class, 'uuid'));
        $this->fields['areaCountry'] = new ReferenceField('areaCountryUuid', 'uuid', \Shopware\AreaCountry\Writer\Resource\AreaCountryResource::class);
        $this->fields['areaCountryUuid'] = (new FkField('area_country_uuid', \Shopware\AreaCountry\Writer\Resource\AreaCountryResource::class, 'uuid'));
        $this->fields['areaCountryState'] = new ReferenceField('areaCountryStateUuid', 'uuid', \Shopware\AreaCountryState\Writer\Resource\AreaCountryStateResource::class);
        $this->fields['areaCountryStateUuid'] = (new FkField('area_country_state_uuid', \Shopware\AreaCountryState\Writer\Resource\AreaCountryStateResource::class, 'uuid'));
        $this->fields['tax'] = new ReferenceField('taxUuid', 'uuid', \Shopware\Tax\Writer\Resource\TaxResource::class);
        $this->fields['taxUuid'] = (new FkField('tax_uuid', \Shopware\Tax\Writer\Resource\TaxResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['customerGroup'] = new ReferenceField('customerGroupUuid', 'uuid', \Shopware\CustomerGroup\Writer\Resource\CustomerGroupResource::class);
        $this->fields['customerGroupUuid'] = (new FkField('customer_group_uuid', \Shopware\CustomerGroup\Writer\Resource\CustomerGroupResource::class, 'uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleTranslationResource::class, 'languageUuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Area\Writer\Resource\AreaResource::class,
            \Shopware\AreaCountry\Writer\Resource\AreaCountryResource::class,
            \Shopware\AreaCountryState\Writer\Resource\AreaCountryStateResource::class,
            \Shopware\Tax\Writer\Resource\TaxResource::class,
            \Shopware\CustomerGroup\Writer\Resource\CustomerGroupResource::class,
            \Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleResource::class,
            \Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleTranslationResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\TaxAreaRule\Event\TaxAreaRuleWrittenEvent
    {
        $event = new \Shopware\TaxAreaRule\Event\TaxAreaRuleWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Area\Writer\Resource\AreaResource::class])) {
            $event->addEvent(\Shopware\Area\Writer\Resource\AreaResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\AreaCountry\Writer\Resource\AreaCountryResource::class])) {
            $event->addEvent(\Shopware\AreaCountry\Writer\Resource\AreaCountryResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\AreaCountryState\Writer\Resource\AreaCountryStateResource::class])) {
            $event->addEvent(\Shopware\AreaCountryState\Writer\Resource\AreaCountryStateResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Tax\Writer\Resource\TaxResource::class])) {
            $event->addEvent(\Shopware\Tax\Writer\Resource\TaxResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\CustomerGroup\Writer\Resource\CustomerGroupResource::class])) {
            $event->addEvent(\Shopware\CustomerGroup\Writer\Resource\CustomerGroupResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleResource::class])) {
            $event->addEvent(\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleTranslationResource::class])) {
            $event->addEvent(\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleTranslationResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }

    public function getDefaults(string $type): array
    {
        if (self::FOR_UPDATE === $type) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
            ];
        }

        if (self::FOR_INSERT === $type) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
                self::CREATED_AT_FIELD => new \DateTime(),
            ];
        }

        throw new \InvalidArgumentException('Unable to generate default values, wrong type submitted');
    }
}
