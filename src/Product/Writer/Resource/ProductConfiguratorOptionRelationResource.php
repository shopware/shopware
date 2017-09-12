<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

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

class ProductConfiguratorOptionRelationResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const PRODUCT_ID_FIELD = 'productId';
    protected const OPTION_ID_FIELD = 'optionId';

    public function __construct()
    {
        parent::__construct('product_configurator_option_relation');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::PRODUCT_ID_FIELD] = (new IntField('product_id'))->setFlags(new Required());
        $this->fields[self::OPTION_ID_FIELD] = (new IntField('option_id'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductConfiguratorOptionRelationResource::class
        ];
    }
}
