<?php declare(strict_types=1);

namespace Shopware\Product\Gateway\Resource;

use Shopware\Framework\Api2\ApiFlag\Required;
use Shopware\Framework\Api2\Field\FkField;
use Shopware\Framework\Api2\Field\IntField;
use Shopware\Framework\Api2\Field\ReferenceField;
use Shopware\Framework\Api2\Field\StringField;
use Shopware\Framework\Api2\Field\BoolField;
use Shopware\Framework\Api2\Field\DateField;
use Shopware\Framework\Api2\Field\SubresourceField;
use Shopware\Framework\Api2\Field\LongTextField;
use Shopware\Framework\Api2\Field\LongTextWithHtmlField;
use Shopware\Framework\Api2\Field\FloatField;
use Shopware\Framework\Api2\Field\TranslatedField;
use Shopware\Framework\Api2\Field\UuidField;
use Shopware\Framework\Api2\Resource\ApiResource;

class ProductConfiguratorTemplatePriceResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('product_configurator_template_price');
        
        $this->fields['templateId'] = new IntField('template_id');
        $this->fields['customerGroupKey'] = (new StringField('customer_group_key'))->setFlags(new Required());
        $this->fields['from'] = (new IntField('from'))->setFlags(new Required());
        $this->fields['to'] = (new StringField('to'))->setFlags(new Required());
        $this->fields['price'] = new FloatField('price');
        $this->fields['pseudoprice'] = new FloatField('pseudoprice');
        $this->fields['percent'] = new FloatField('percent');
        $this->primaryKeyFields['uuid'] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields['attributes'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductConfiguratorTemplatePriceAttributeResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Gateway\Resource\ProductConfiguratorTemplatePriceResource::class,
            \Shopware\Product\Gateway\Resource\ProductConfiguratorTemplatePriceAttributeResource::class
        ];
    }
}
