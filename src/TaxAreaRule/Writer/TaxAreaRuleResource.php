<?php declare(strict_types=1);

namespace Shopware\TaxAreaRule\Writer;

use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\LongTextWithHtmlField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Resource;

class TaxAreaRuleResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const AREA_ID_FIELD = 'areaId';
    protected const AREA_COUNTRY_ID_FIELD = 'areaCountryId';
    protected const AREA_COUNTRY_STATE_ID_FIELD = 'areaCountryStateId';
    protected const TAX_ID_FIELD = 'taxId';
    protected const CUSTOMER_GROUP_ID_FIELD = 'customerGroupId';
    protected const TAX_RATE_FIELD = 'taxRate';
    protected const NAME_FIELD = 'name';
    protected const ACTIVE_FIELD = 'active';

    public function __construct()
    {
        parent::__construct('tax_area_rule');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::AREA_ID_FIELD] = new IntField('area_id');
        $this->fields[self::AREA_COUNTRY_ID_FIELD] = new IntField('area_country_id');
        $this->fields[self::AREA_COUNTRY_STATE_ID_FIELD] = new IntField('area_country_state_id');
        $this->fields[self::TAX_ID_FIELD] = (new IntField('tax_id'))->setFlags(new Required());
        $this->fields[self::CUSTOMER_GROUP_ID_FIELD] = (new IntField('customer_group_id'))->setFlags(new Required());
        $this->fields[self::TAX_RATE_FIELD] = (new FloatField('tax_rate'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::ACTIVE_FIELD] = (new BoolField('active'))->setFlags(new Required());
        $this->fields['area'] = new ReferenceField('areaUuid', 'uuid', \Shopware\Area\Writer\AreaResource::class);
        $this->fields['areaUuid'] = (new FkField('area_uuid', \Shopware\Area\Writer\AreaResource::class, 'uuid'));
        $this->fields['areaCountry'] = new ReferenceField('areaCountryUuid', 'uuid', \Shopware\AreaCountry\Writer\AreaCountryResource::class);
        $this->fields['areaCountryUuid'] = (new FkField('area_country_uuid', \Shopware\AreaCountry\Writer\AreaCountryResource::class, 'uuid'));
        $this->fields['areaCountryState'] = new ReferenceField('areaCountryStateUuid', 'uuid', \Shopware\AreaCountryState\Writer\AreaCountryStateResource::class);
        $this->fields['areaCountryStateUuid'] = (new FkField('area_country_state_uuid', \Shopware\AreaCountryState\Writer\AreaCountryStateResource::class, 'uuid'));
        $this->fields['tax'] = new ReferenceField('taxUuid', 'uuid', \Shopware\Tax\Writer\TaxResource::class);
        $this->fields['taxUuid'] = (new FkField('tax_uuid', \Shopware\Tax\Writer\TaxResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['customerGroup'] = new ReferenceField('customerGroupUuid', 'uuid', \Shopware\CustomerGroup\Writer\CustomerGroupResource::class);
        $this->fields['customerGroupUuid'] = (new FkField('customer_group_uuid', \Shopware\CustomerGroup\Writer\CustomerGroupResource::class, 'uuid'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Area\Writer\AreaResource::class,
            \Shopware\AreaCountry\Writer\AreaCountryResource::class,
            \Shopware\AreaCountryState\Writer\AreaCountryStateResource::class,
            \Shopware\Tax\Writer\TaxResource::class,
            \Shopware\CustomerGroup\Writer\CustomerGroupResource::class,
            \Shopware\TaxAreaRule\Writer\TaxAreaRuleResource::class
        ];
    }
}
