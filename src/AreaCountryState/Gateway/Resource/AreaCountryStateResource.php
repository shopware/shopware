<?php declare(strict_types=1);

namespace Shopware\AreaCountryState\Gateway\Resource;

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

class AreaCountryStateResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const NAME_FIELD = 'name';
    protected const SHORT_CODE_FIELD = 'shortCode';
    protected const POSITION_FIELD = 'position';
    protected const ACTIVE_FIELD = 'active';

    public function __construct()
    {
        parent::__construct('area_country_state');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = new StringField('name');
        $this->fields[self::SHORT_CODE_FIELD] = (new StringField('short_code'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = new IntField('position');
        $this->fields[self::ACTIVE_FIELD] = new BoolField('active');
        $this->fields['areaCountry'] = new ReferenceField('areaCountryUuid', 'uuid', \Shopware\AreaCountry\Gateway\Resource\AreaCountryResource::class);
        $this->fields['areaCountryUuid'] = (new FkField('area_country_uuid', \Shopware\AreaCountry\Gateway\Resource\AreaCountryResource::class, 'uuid'));
        $this->fields['customerAddresss'] = new SubresourceField(\Shopware\CustomerAddress\Gateway\Resource\CustomerAddressResource::class);
        $this->fields['taxAreaRules'] = new SubresourceField(\Shopware\TaxAreaRule\Gateway\Resource\TaxAreaRuleResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\AreaCountry\Gateway\Resource\AreaCountryResource::class,
            \Shopware\AreaCountryState\Gateway\Resource\AreaCountryStateResource::class,
            \Shopware\CustomerAddress\Gateway\Resource\CustomerAddressResource::class,
            \Shopware\TaxAreaRule\Gateway\Resource\TaxAreaRuleResource::class
        ];
    }
}
