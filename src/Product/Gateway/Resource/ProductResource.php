<?php declare(strict_types=1);

namespace Shopware\Product\Gateway\Resource;

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

class ProductResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const SHIPPING_TIME_FIELD = 'shippingTime';
    protected const CREATED_AT_FIELD = 'createdAt';
    protected const ACTIVE_FIELD = 'active';
    protected const PSEUDO_SALES_FIELD = 'pseudoSales';
    protected const TOPSELLER_FIELD = 'topseller';
    protected const UPDATED_AT_FIELD = 'updatedAt';
    protected const PRICE_GROUP_ID_FIELD = 'priceGroupId';
    protected const LAST_STOCK_FIELD = 'lastStock';
    protected const NOTIFICATION_FIELD = 'notification';
    protected const TEMPLATE_FIELD = 'template';
    protected const MODE_FIELD = 'mode';
    protected const AVAILABLE_FROM_FIELD = 'availableFrom';
    protected const AVAILABLE_TO_FIELD = 'availableTo';
    protected const CONFIGURATOR_SET_ID_FIELD = 'configuratorSetId';
    protected const NAME_FIELD = 'name';
    protected const KEYWORDS_FIELD = 'keywords';
    protected const DESCRIPTION_FIELD = 'description';
    protected const DESCRIPTION_LONG_FIELD = 'descriptionLong';
    protected const META_TITLE_FIELD = 'metaTitle';

    public function __construct()
    {
        parent::__construct('product');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::SHIPPING_TIME_FIELD] = new StringField('shipping_time');
        $this->fields[self::CREATED_AT_FIELD] = new DateField('created_at');
        $this->fields[self::ACTIVE_FIELD] = new BoolField('active');
        $this->fields[self::PSEUDO_SALES_FIELD] = new IntField('pseudo_sales');
        $this->fields[self::TOPSELLER_FIELD] = new BoolField('topseller');
        $this->fields[self::UPDATED_AT_FIELD] = (new DateField('updated_at'))->setFlags(new Required());
        $this->fields[self::PRICE_GROUP_ID_FIELD] = new IntField('price_group_id');
        $this->fields[self::LAST_STOCK_FIELD] = (new BoolField('last_stock'))->setFlags(new Required());
        $this->fields[self::NOTIFICATION_FIELD] = (new BoolField('notification'))->setFlags(new Required());
        $this->fields[self::TEMPLATE_FIELD] = (new StringField('template'))->setFlags(new Required());
        $this->fields[self::MODE_FIELD] = (new IntField('mode'))->setFlags(new Required());
        $this->fields[self::AVAILABLE_FROM_FIELD] = new DateField('available_from');
        $this->fields[self::AVAILABLE_TO_FIELD] = new DateField('available_to');
        $this->fields[self::CONFIGURATOR_SET_ID_FIELD] = new IntField('configurator_set_id');
        $this->fields['blogProducts'] = new SubresourceField(\Shopware\Framework\Write\Resource\BlogProductResource::class);
        $this->fields['filterProducts'] = new SubresourceField(\Shopware\Framework\Write\Resource\FilterProductResource::class);
        $this->fields['manufacturer'] = new ReferenceField('manufacturerUuid', 'uuid', \Shopware\ProductManufacturer\Gateway\Resource\ProductManufacturerResource::class);
        $this->fields['manufacturerUuid'] = (new FkField('product_manufacturer_uuid', \Shopware\ProductManufacturer\Gateway\Resource\ProductManufacturerResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['tax'] = new ReferenceField('taxUuid', 'uuid', \Shopware\Tax\Gateway\Resource\TaxResource::class);
        $this->fields['taxUuid'] = (new FkField('tax_uuid', \Shopware\Tax\Gateway\Resource\TaxResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['filterGroup'] = new ReferenceField('filterGroupUuid', 'uuid', \Shopware\Framework\Write\Resource\FilterResource::class);
        $this->fields['filterGroupUuid'] = (new FkField('filter_group_uuid', \Shopware\Framework\Write\Resource\FilterResource::class, 'uuid'));
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Gateway\Resource\ShopResource::class, 'uuid');
        $this->fields[self::KEYWORDS_FIELD] = new TranslatedField('keywords', \Shopware\Shop\Gateway\Resource\ShopResource::class, 'uuid');
        $this->fields[self::DESCRIPTION_FIELD] = new TranslatedField('description', \Shopware\Shop\Gateway\Resource\ShopResource::class, 'uuid');
        $this->fields[self::DESCRIPTION_LONG_FIELD] = new TranslatedField('descriptionLong', \Shopware\Shop\Gateway\Resource\ShopResource::class, 'uuid');
        $this->fields[self::META_TITLE_FIELD] = new TranslatedField('metaTitle', \Shopware\Shop\Gateway\Resource\ShopResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\Product\Gateway\Resource\ProductTranslationResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['accessorys'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductAccessoryResource::class);
        $this->fields['attachments'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductAttachmentResource::class);
        $this->fields['avoidCustomerGroups'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductAvoidCustomerGroupResource::class);
        $this->fields['categorys'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductCategoryResource::class);
        $this->fields['categorySeos'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductCategorySeoResource::class);
        $this->fields['details'] = new SubresourceField(\Shopware\ProductDetail\Gateway\Resource\ProductDetailResource::class);
        $this->fields['esds'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductEsdResource::class);
        $this->fields['links'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductLinkResource::class);
        $this->fields['medias'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductMediaResource::class);
        $this->fields['similars'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductSimilarResource::class);
        $this->fields['streamAssignments'] = new SubresourceField(\Shopware\ProductStream\Gateway\Resource\ProductStreamAssignmentResource::class);
        $this->fields['streamTabs'] = new SubresourceField(\Shopware\ProductStream\Gateway\Resource\ProductStreamTabResource::class);
        $this->fields['votes'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductVoteResource::class);
        $this->fields['statisticProductImpressions'] = new SubresourceField(\Shopware\Framework\Write\Resource\StatisticProductImpressionResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\BlogProductResource::class,
            \Shopware\Framework\Write\Resource\FilterProductResource::class,
            \Shopware\ProductManufacturer\Gateway\Resource\ProductManufacturerResource::class,
            \Shopware\Tax\Gateway\Resource\TaxResource::class,
            \Shopware\Framework\Write\Resource\FilterResource::class,
            \Shopware\Product\Gateway\Resource\ProductResource::class,
            \Shopware\Product\Gateway\Resource\ProductTranslationResource::class,
            \Shopware\Product\Gateway\Resource\ProductAccessoryResource::class,
            \Shopware\Product\Gateway\Resource\ProductAttachmentResource::class,
            \Shopware\Product\Gateway\Resource\ProductAvoidCustomerGroupResource::class,
            \Shopware\Product\Gateway\Resource\ProductCategoryResource::class,
            \Shopware\Product\Gateway\Resource\ProductCategorySeoResource::class,
            \Shopware\ProductDetail\Gateway\Resource\ProductDetailResource::class,
            \Shopware\Product\Gateway\Resource\ProductEsdResource::class,
            \Shopware\Product\Gateway\Resource\ProductLinkResource::class,
            \Shopware\Product\Gateway\Resource\ProductMediaResource::class,
            \Shopware\Product\Gateway\Resource\ProductSimilarResource::class,
            \Shopware\ProductStream\Gateway\Resource\ProductStreamAssignmentResource::class,
            \Shopware\ProductStream\Gateway\Resource\ProductStreamTabResource::class,
            \Shopware\Product\Gateway\Resource\ProductVoteResource::class,
            \Shopware\Framework\Write\Resource\StatisticProductImpressionResource::class
        ];
    }    
    
    public function getDefaults(string $type): array {
        if($type === self::FOR_UPDATE) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
            ];
        }

        if($type === self::FOR_INSERT) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
                self::CREATED_AT_FIELD => new \DateTime(),
            ];
        }

        throw new \InvalidArgumentException('Unable to generate default values, wrong type submitted');
    }
}
