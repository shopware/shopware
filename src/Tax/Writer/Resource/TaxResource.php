<?php declare(strict_types=1);

namespace Shopware\Tax\Writer\Resource;

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

class TaxResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const RATE_FIELD = 'rate';
    protected const DESCRIPTION_FIELD = 'description';

    public function __construct()
    {
        parent::__construct('tax');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::RATE_FIELD] = (new FloatField('tax_rate'))->setFlags(new Required());
        $this->fields[self::DESCRIPTION_FIELD] = (new StringField('description'))->setFlags(new Required());
        $this->fields['products'] = new SubresourceField(\Shopware\Product\Writer\Resource\ProductResource::class);
        $this->fields['areaRules'] = new SubresourceField(\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductResource::class,
            \Shopware\Tax\Writer\Resource\TaxResource::class,
            \Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleResource::class
        ];
    }
}
