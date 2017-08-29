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

class ProductConfiguratorTemplateResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('product_configurator_template');
        
        $this->primaryKeyFields['uuid'] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields['productId'] = new IntField('product_id');
        $this->fields['orderNumber'] = (new StringField('order_number'))->setFlags(new Required());
        $this->fields['suppliernumber'] = new StringField('suppliernumber');
        $this->fields['additionaltext'] = new StringField('additionaltext');
        $this->fields['impressions'] = new IntField('impressions');
        $this->fields['sales'] = new IntField('sales');
        $this->fields['active'] = new BoolField('active');
        $this->fields['instock'] = new IntField('instock');
        $this->fields['stockmin'] = new IntField('stockmin');
        $this->fields['weight'] = new FloatField('weight');
        $this->fields['position'] = (new IntField('position'))->setFlags(new Required());
        $this->fields['width'] = new FloatField('width');
        $this->fields['height'] = new FloatField('height');
        $this->fields['length'] = new FloatField('length');
        $this->fields['ean'] = new StringField('ean');
        $this->fields['unitId'] = new IntField('unit_id');
        $this->fields['purchasesteps'] = new IntField('purchasesteps');
        $this->fields['maxpurchase'] = new IntField('maxpurchase');
        $this->fields['minpurchase'] = new IntField('minpurchase');
        $this->fields['purchaseunit'] = new FloatField('purchaseunit');
        $this->fields['referenceunit'] = new FloatField('referenceunit');
        $this->fields['packunit'] = new StringField('packunit');
        $this->fields['releasedate'] = new DateField('releasedate');
        $this->fields['shippingfree'] = new IntField('shippingfree');
        $this->fields['shippingtime'] = new StringField('shippingtime');
        $this->fields['purchaseprice'] = new FloatField('purchaseprice');
        $this->fields['attributes'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductConfiguratorTemplateAttributeResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Gateway\Resource\ProductConfiguratorTemplateResource::class,
            \Shopware\Product\Gateway\Resource\ProductConfiguratorTemplateAttributeResource::class
        ];
    }
}
