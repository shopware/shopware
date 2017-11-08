<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Api\Write\Field\BoolField;
use Shopware\Api\Write\Field\DateField;
use Shopware\Api\Write\Field\FkField;
use Shopware\Api\Write\Field\FloatField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\ReferenceField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Field\SubresourceField;
use Shopware\Api\Write\Field\TranslatedField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Writer\Resource\BlogProductWriteResource;
use Shopware\Framework\Writer\Resource\FilterProductWriteResource;
use Shopware\Framework\Writer\Resource\FilterWriteResource;
use Shopware\Framework\Writer\Resource\PremiumProductWriteResource;
use Shopware\Framework\Writer\Resource\StatisticProductImpressionWriteResource;
use Shopware\Product\Event\ProductWrittenEvent;
use Shopware\ProductManufacturer\Writer\Resource\ProductManufacturerWriteResource;
use Shopware\ProductMedia\Writer\Resource\ProductMediaWriteResource;
use Shopware\ProductPrice\Writer\Resource\ProductPriceWriteResource;
use Shopware\ProductStream\Writer\Resource\ProductStreamAssignmentWriteResource;
use Shopware\ProductStream\Writer\Resource\ProductStreamTabWriteResource;
use Shopware\ProductVote\Writer\Resource\ProductVoteWriteResource;
use Shopware\Shop\Writer\Resource\ShopWriteResource;
use Shopware\Tax\Writer\Resource\TaxWriteResource;

class ProductWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const IS_MAIN_FIELD = 'isMain';
    protected const ACTIVE_FIELD = 'active';
    protected const PRICE_GROUP_UUID_FIELD = 'priceGroupUuid';
    protected const UNIT_UUID_FIELD = 'unitUuid';
    protected const SUPPLIER_NUMBER_FIELD = 'supplierNumber';
    protected const EAN_FIELD = 'ean';
    protected const STOCK_FIELD = 'stock';
    protected const IS_CLOSEOUT_FIELD = 'isCloseout';
    protected const MIN_STOCK_FIELD = 'minStock';
    protected const PURCHASE_STEPS_FIELD = 'purchaseSteps';
    protected const MAX_PURCHASE_FIELD = 'maxPurchase';
    protected const MIN_PURCHASE_FIELD = 'minPurchase';
    protected const PURCHASE_UNIT_FIELD = 'purchaseUnit';
    protected const REFERENCE_UNIT_FIELD = 'referenceUnit';
    protected const SHIPPING_FREE_FIELD = 'shippingFree';
    protected const PURCHASE_PRICE_FIELD = 'purchasePrice';
    protected const PSEUDO_SALES_FIELD = 'pseudoSales';
    protected const MARK_AS_TOPSELLER_FIELD = 'markAsTopseller';
    protected const SALES_FIELD = 'sales';
    protected const POSITION_FIELD = 'position';
    protected const WEIGHT_FIELD = 'weight';
    protected const WIDTH_FIELD = 'width';
    protected const HEIGHT_FIELD = 'height';
    protected const LENGTH_FIELD = 'length';
    protected const TEMPLATE_FIELD = 'template';
    protected const ALLOW_NOTIFICATION_FIELD = 'allowNotification';
    protected const RELEASE_DATE_FIELD = 'releaseDate';
    protected const ADDITIONAL_TEXT_FIELD = 'additionalText';
    protected const NAME_FIELD = 'name';
    protected const KEYWORDS_FIELD = 'keywords';
    protected const DESCRIPTION_FIELD = 'description';
    protected const DESCRIPTION_LONG_FIELD = 'descriptionLong';
    protected const META_TITLE_FIELD = 'metaTitle';
    protected const PACK_UNIT_FIELD = 'packUnit';

    public function __construct()
    {
        parent::__construct('product');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::IS_MAIN_FIELD] = new BoolField('is_main');
        $this->fields[self::ACTIVE_FIELD] = new BoolField('active');
        $this->fields[self::PRICE_GROUP_UUID_FIELD] = new StringField('price_group_uuid');
        $this->fields[self::UNIT_UUID_FIELD] = new StringField('unit_uuid');
        $this->fields[self::SUPPLIER_NUMBER_FIELD] = new StringField('supplier_number');
        $this->fields[self::EAN_FIELD] = new StringField('ean');
        $this->fields[self::STOCK_FIELD] = new IntField('stock');
        $this->fields[self::IS_CLOSEOUT_FIELD] = new BoolField('is_closeout');
        $this->fields[self::MIN_STOCK_FIELD] = new IntField('min_stock');
        $this->fields[self::PURCHASE_STEPS_FIELD] = new IntField('purchase_steps');
        $this->fields[self::MAX_PURCHASE_FIELD] = new IntField('max_purchase');
        $this->fields[self::MIN_PURCHASE_FIELD] = new IntField('min_purchase');
        $this->fields[self::PURCHASE_UNIT_FIELD] = new FloatField('purchase_unit');
        $this->fields[self::REFERENCE_UNIT_FIELD] = new FloatField('reference_unit');
        $this->fields[self::SHIPPING_FREE_FIELD] = new BoolField('shipping_free');
        $this->fields[self::PURCHASE_PRICE_FIELD] = new FloatField('purchase_price');
        $this->fields[self::PSEUDO_SALES_FIELD] = new IntField('pseudo_sales');
        $this->fields[self::MARK_AS_TOPSELLER_FIELD] = new BoolField('mark_as_topseller');
        $this->fields[self::SALES_FIELD] = new IntField('sales');
        $this->fields[self::POSITION_FIELD] = new IntField('position');
        $this->fields[self::WEIGHT_FIELD] = new FloatField('weight');
        $this->fields[self::WIDTH_FIELD] = new FloatField('width');
        $this->fields[self::HEIGHT_FIELD] = new FloatField('height');
        $this->fields[self::LENGTH_FIELD] = new FloatField('length');
        $this->fields[self::TEMPLATE_FIELD] = new StringField('template');
        $this->fields[self::ALLOW_NOTIFICATION_FIELD] = new BoolField('allow_notification');
        $this->fields[self::RELEASE_DATE_FIELD] = new DateField('release_date');
        $this->fields['blogProducts'] = new SubresourceField(BlogProductWriteResource::class);
        $this->fields['filterProducts'] = new SubresourceField(FilterProductWriteResource::class);
        $this->fields['premiumProducts'] = new SubresourceField(PremiumProductWriteResource::class);
        $this->fields['tax'] = new ReferenceField('taxUuid', 'uuid', TaxWriteResource::class);
        $this->fields['taxUuid'] = (new FkField('tax_uuid', TaxWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['manufacturer'] = new ReferenceField('manufacturerUuid', 'uuid', ProductManufacturerWriteResource::class);
        $this->fields['manufacturerUuid'] = (new FkField('product_manufacturer_uuid', ProductManufacturerWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['filterGroup'] = new ReferenceField('filterGroupUuid', 'uuid', FilterWriteResource::class);
        $this->fields['filterGroupUuid'] = (new FkField('filter_group_uuid', FilterWriteResource::class, 'uuid'));
        $this->fields[self::ADDITIONAL_TEXT_FIELD] = new TranslatedField('additionalText', ShopWriteResource::class, 'uuid');
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', ShopWriteResource::class, 'uuid');
        $this->fields[self::KEYWORDS_FIELD] = new TranslatedField('keywords', ShopWriteResource::class, 'uuid');
        $this->fields[self::DESCRIPTION_FIELD] = new TranslatedField('description', ShopWriteResource::class, 'uuid');
        $this->fields[self::DESCRIPTION_LONG_FIELD] = new TranslatedField('descriptionLong', ShopWriteResource::class, 'uuid');
        $this->fields[self::META_TITLE_FIELD] = new TranslatedField('metaTitle', ShopWriteResource::class, 'uuid');
        $this->fields[self::PACK_UNIT_FIELD] = new TranslatedField('packUnit', ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(ProductTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['accessories'] = new SubresourceField(ProductAccessoryWriteResource::class);
        $this->fields['attachments'] = new SubresourceField(ProductAttachmentWriteResource::class);
        $this->fields['avoidCustomerGroups'] = new SubresourceField(ProductAvoidCustomerGroupWriteResource::class);
        $this->fields['categories'] = new SubresourceField(ProductCategoryWriteResource::class);
        $this->fields['categorySeos'] = new SubresourceField(ProductCategorySeoWriteResource::class);
        $this->fields['esds'] = new SubresourceField(ProductEsdWriteResource::class);
        $this->fields['links'] = new SubresourceField(ProductLinkWriteResource::class);
        $this->fields['media'] = new SubresourceField(ProductMediaWriteResource::class);
        $this->fields['prices'] = new SubresourceField(ProductPriceWriteResource::class);
        $this->fields['similars'] = new SubresourceField(ProductSimilarWriteResource::class);
        $this->fields['streamAssignments'] = new SubresourceField(ProductStreamAssignmentWriteResource::class);
        $this->fields['streamTabs'] = new SubresourceField(ProductStreamTabWriteResource::class);
        $this->fields['votes'] = new SubresourceField(ProductVoteWriteResource::class);
        $this->fields['statisticProductImpressions'] = new SubresourceField(StatisticProductImpressionWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            BlogProductWriteResource::class,
            FilterProductWriteResource::class,
            PremiumProductWriteResource::class,
            TaxWriteResource::class,
            ProductManufacturerWriteResource::class,
            FilterWriteResource::class,
            self::class,
            ProductTranslationWriteResource::class,
            ProductAccessoryWriteResource::class,
            ProductAttachmentWriteResource::class,
            ProductAvoidCustomerGroupWriteResource::class,
            ProductCategoryWriteResource::class,
            ProductCategorySeoWriteResource::class,
            ProductEsdWriteResource::class,
            ProductLinkWriteResource::class,
            ProductMediaWriteResource::class,
            ProductPriceWriteResource::class,
            ProductSimilarWriteResource::class,
            ProductStreamAssignmentWriteResource::class,
            ProductStreamTabWriteResource::class,
            ProductVoteWriteResource::class,
            StatisticProductImpressionWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ProductWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new ProductWrittenEvent($uuids, $context, $rawData, $errors);

        unset($updates[self::class]);

        /**
         * @var WriteResource
         * @var string[]      $identifiers
         */
        foreach ($updates as $class => $identifiers) {
            if (!array_key_exists($class, $updates) || count($updates[$class]) === 0) {
                continue;
            }

            $event->addEvent($class::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
