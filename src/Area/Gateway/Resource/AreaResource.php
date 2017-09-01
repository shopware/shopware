<?php declare(strict_types=1);

namespace Shopware\Area\Gateway\Resource;

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

class AreaResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const NAME_FIELD = 'name';
    protected const ACTIVE_FIELD = 'active';

    public function __construct()
    {
        parent::__construct('area');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = new StringField('name');
        $this->fields[self::ACTIVE_FIELD] = new BoolField('active');
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Gateway\Resource\ShopResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\Area\Gateway\Resource\AreaTranslationResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['countrys'] = new SubresourceField(\Shopware\AreaCountry\Gateway\Resource\AreaCountryResource::class);
        $this->fields['taxAreaRules'] = new SubresourceField(\Shopware\TaxAreaRule\Gateway\Resource\TaxAreaRuleResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Area\Gateway\Resource\AreaResource::class,
            \Shopware\Area\Gateway\Resource\AreaTranslationResource::class,
            \Shopware\AreaCountry\Gateway\Resource\AreaCountryResource::class,
            \Shopware\TaxAreaRule\Gateway\Resource\TaxAreaRuleResource::class
        ];
    }
}
