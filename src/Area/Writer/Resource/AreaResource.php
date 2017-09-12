<?php declare(strict_types=1);

namespace Shopware\Area\Writer\Resource;

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
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields['translations'] = new SubresourceField(\Shopware\Area\Writer\Resource\AreaTranslationResource::class, 'languageUuid');
        $this->fields['countrys'] = new SubresourceField(\Shopware\AreaCountry\Writer\Resource\AreaCountryResource::class);
        $this->fields['taxAreaRules'] = new SubresourceField(\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Area\Writer\Resource\AreaResource::class,
            \Shopware\Area\Writer\Resource\AreaTranslationResource::class,
            \Shopware\AreaCountry\Writer\Resource\AreaCountryResource::class,
            \Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleResource::class
        ];
    }
}
