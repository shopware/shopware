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

class ProductResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('product');
        
        $this->primaryKeyFields['uuid'] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields['shippingTime'] = new StringField('shipping_time');
        $this->fields['createdAt'] = new DateField('created_at');
        $this->fields['active'] = new BoolField('active');
        $this->fields['pseudoSales'] = new IntField('pseudo_sales');
        $this->fields['topseller'] = new IntField('topseller');
        $this->fields['updatedAt'] = (new DateField('updated_at'))->setFlags(new Required());
        $this->fields['priceGroupId'] = new IntField('price_group_id');
        $this->fields['filterGroupUuid'] = new StringField('filter_group_uuid');
        $this->fields['lastStock'] = (new IntField('last_stock'))->setFlags(new Required());
        $this->fields['crossbundlelook'] = (new IntField('crossbundlelook'))->setFlags(new Required());
        $this->fields['notification'] = (new IntField('notification'))->setFlags(new Required());
        $this->fields['template'] = (new StringField('template'))->setFlags(new Required());
        $this->fields['mode'] = (new IntField('mode'))->setFlags(new Required());
        $this->fields['availableFrom'] = new DateField('available_from');
        $this->fields['availableTo'] = new DateField('available_to');
        $this->fields['configuratorSetId'] = new IntField('configurator_set_id');
        $this->fields['manufacturer'] = new ReferenceField('manufacturerUuid', 'uuid', \Shopware\Product\Gateway\Resource\ProductManufacturerResource::class);
        $this->fields['manufacturerUuid'] = (new FkField('product_manufacturer_uuid', \Shopware\Product\Gateway\Resource\ProductManufacturerResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['tax'] = new ReferenceField('taxUuid', 'uuid', \Shopware\Framework\Api2\Resource\CoreTaxResource::class);
        $this->fields['taxUuid'] = (new FkField('tax_uuid', \Shopware\Framework\Api2\Resource\CoreTaxResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['name'] = new TranslatedField('name', \Shopware\Framework\Api2\Resource\CoreShopsResource::class, 'uuid');
        $this->fields['keywords'] = new TranslatedField('keywords', \Shopware\Framework\Api2\Resource\CoreShopsResource::class, 'uuid');
        $this->fields['description'] = new TranslatedField('description', \Shopware\Framework\Api2\Resource\CoreShopsResource::class, 'uuid');
        $this->fields['descriptionLong'] = new TranslatedField('descriptionLong', \Shopware\Framework\Api2\Resource\CoreShopsResource::class, 'uuid');
        $this->fields['metaTitle'] = new TranslatedField('metaTitle', \Shopware\Framework\Api2\Resource\CoreShopsResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\Product\Gateway\Resource\ProductTranslationResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['alsoBoughtRos'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductAlsoBoughtRoResource::class);
        $this->fields['avoidCustomerGroups'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductAvoidCustomerGroupResource::class);
        $this->fields['categorys'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductCategoryResource::class);
        $this->fields['categoryRos'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductCategoryRoResource::class);
        $this->fields['categorySeos'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductCategorySeoResource::class);
        $this->fields['details'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductDetailResource::class);
        $this->fields['downloads'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductDownloadResource::class);
        $this->fields['esds'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductEsdResource::class);
        $this->fields['images'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductImageResource::class);
        $this->fields['informations'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductInformationResource::class);
        $this->fields['relationships'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductRelationshipResource::class);
        $this->fields['similars'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductSimilarResource::class);
        $this->fields['similarShownRos'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductSimilarShownRoResource::class);
        $this->fields['topSellerRos'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductTopSellerRoResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Gateway\Resource\ProductManufacturerResource::class,
            \Shopware\Framework\Api2\Resource\CoreTaxResource::class,
            \Shopware\Product\Gateway\Resource\ProductResource::class,
            \Shopware\Product\Gateway\Resource\ProductTranslationResource::class,
            \Shopware\Product\Gateway\Resource\ProductAlsoBoughtRoResource::class,
            \Shopware\Product\Gateway\Resource\ProductAvoidCustomerGroupResource::class,
            \Shopware\Product\Gateway\Resource\ProductCategoryResource::class,
            \Shopware\Product\Gateway\Resource\ProductCategoryRoResource::class,
            \Shopware\Product\Gateway\Resource\ProductCategorySeoResource::class,
            \Shopware\Product\Gateway\Resource\ProductDetailResource::class,
            \Shopware\Product\Gateway\Resource\ProductDownloadResource::class,
            \Shopware\Product\Gateway\Resource\ProductEsdResource::class,
            \Shopware\Product\Gateway\Resource\ProductImageResource::class,
            \Shopware\Product\Gateway\Resource\ProductInformationResource::class,
            \Shopware\Product\Gateway\Resource\ProductRelationshipResource::class,
            \Shopware\Product\Gateway\Resource\ProductSimilarResource::class,
            \Shopware\Product\Gateway\Resource\ProductSimilarShownRoResource::class,
            \Shopware\Product\Gateway\Resource\ProductTopSellerRoResource::class
        ];
    }
}
