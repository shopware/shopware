<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource\BlogProductWriteResource;
use Shopware\Framework\Write\Resource\FilterProductWriteResource;
use Shopware\Framework\Write\Resource\FilterWriteResource;
use Shopware\Framework\Write\Resource\StatisticProductImpressionWriteResource;
use Shopware\Framework\Write\WriteResource;
use Shopware\Product\Event\ProductWrittenEvent;
use Shopware\ProductDetail\Writer\Resource\ProductDetailWriteResource;
use Shopware\ProductManufacturer\Writer\Resource\ProductManufacturerWriteResource;
use Shopware\ProductMedia\Writer\Resource\ProductMediaWriteResource;
use Shopware\ProductStream\Writer\Resource\ProductStreamAssignmentWriteResource;
use Shopware\ProductStream\Writer\Resource\ProductStreamTabWriteResource;
use Shopware\ProductVote\Writer\Resource\ProductVoteWriteResource;
use Shopware\Shop\Writer\Resource\ShopWriteResource;
use Shopware\Tax\Writer\Resource\TaxWriteResource;

class ProductWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const ACTIVE_FIELD = 'active';
    protected const PSEUDO_SALES_FIELD = 'pseudoSales';
    protected const MARK_AS_TOPSELLER_FIELD = 'markAsTopseller';
    protected const PRICE_GROUP_UUID_FIELD = 'priceGroupUuid';
    protected const IS_CLOSEOUT_FIELD = 'isCloseout';
    protected const ALLOW_NOTIFICATION_FIELD = 'allowNotification';
    protected const TEMPLATE_FIELD = 'template';
    protected const CONFIGURATOR_SET_ID_FIELD = 'configuratorSetId';
    protected const MAIN_DETAIL_UUID_FIELD = 'mainDetailUuid';
    protected const NAME_FIELD = 'name';
    protected const KEYWORDS_FIELD = 'keywords';
    protected const DESCRIPTION_FIELD = 'description';
    protected const DESCRIPTION_LONG_FIELD = 'descriptionLong';
    protected const META_TITLE_FIELD = 'metaTitle';

    public function __construct()
    {
        parent::__construct('product');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::ACTIVE_FIELD] = new BoolField('active');
        $this->fields[self::PSEUDO_SALES_FIELD] = new IntField('pseudo_sales');
        $this->fields[self::MARK_AS_TOPSELLER_FIELD] = new BoolField('mark_as_topseller');
        $this->fields[self::PRICE_GROUP_UUID_FIELD] = new StringField('price_group_uuid');
        $this->fields[self::IS_CLOSEOUT_FIELD] = new BoolField('is_closeout');
        $this->fields[self::ALLOW_NOTIFICATION_FIELD] = new BoolField('allow_notification');
        $this->fields[self::TEMPLATE_FIELD] = new StringField('template');
        $this->fields[self::CONFIGURATOR_SET_ID_FIELD] = new IntField('configurator_set_id');
        $this->fields[self::MAIN_DETAIL_UUID_FIELD] = (new StringField('main_detail_uuid'))->setFlags(new Required());
        $this->fields['blogProducts'] = new SubresourceField(BlogProductWriteResource::class);
        $this->fields['filterProducts'] = new SubresourceField(FilterProductWriteResource::class);
        $this->fields['tax'] = new ReferenceField('taxUuid', 'uuid', TaxWriteResource::class);
        $this->fields['taxUuid'] = (new FkField('tax_uuid', TaxWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['manufacturer'] = new ReferenceField('manufacturerUuid', 'uuid', ProductManufacturerWriteResource::class);
        $this->fields['manufacturerUuid'] = (new FkField('product_manufacturer_uuid', ProductManufacturerWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['filterGroup'] = new ReferenceField('filterGroupUuid', 'uuid', FilterWriteResource::class);
        $this->fields['filterGroupUuid'] = (new FkField('filter_group_uuid', FilterWriteResource::class, 'uuid'));
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', ShopWriteResource::class, 'uuid');
        $this->fields[self::KEYWORDS_FIELD] = new TranslatedField('keywords', ShopWriteResource::class, 'uuid');
        $this->fields[self::DESCRIPTION_FIELD] = new TranslatedField('description', ShopWriteResource::class, 'uuid');
        $this->fields[self::DESCRIPTION_LONG_FIELD] = new TranslatedField('descriptionLong', ShopWriteResource::class, 'uuid');
        $this->fields[self::META_TITLE_FIELD] = new TranslatedField('metaTitle', ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(ProductTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['accessories'] = new SubresourceField(ProductAccessoryWriteResource::class);
        $this->fields['attachments'] = new SubresourceField(ProductAttachmentWriteResource::class);
        $this->fields['avoidCustomerGroups'] = new SubresourceField(ProductAvoidCustomerGroupWriteResource::class);
        $this->fields['categories'] = new SubresourceField(ProductCategoryWriteResource::class);
        $this->fields['categorySeos'] = new SubresourceField(ProductCategorySeoWriteResource::class);
        $this->fields['details'] = new SubresourceField(ProductDetailWriteResource::class);
        $this->fields['esds'] = new SubresourceField(ProductEsdWriteResource::class);
        $this->fields['links'] = new SubresourceField(ProductLinkWriteResource::class);
        $this->fields['media'] = new SubresourceField(ProductMediaWriteResource::class);
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
            ProductDetailWriteResource::class,
            ProductEsdWriteResource::class,
            ProductLinkWriteResource::class,
            ProductMediaWriteResource::class,
            ProductSimilarWriteResource::class,
            ProductStreamAssignmentWriteResource::class,
            ProductStreamTabWriteResource::class,
            ProductVoteWriteResource::class,
            StatisticProductImpressionWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): ProductWrittenEvent
    {
        $event = new ProductWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[BlogProductWriteResource::class])) {
            $event->addEvent(BlogProductWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[FilterProductWriteResource::class])) {
            $event->addEvent(FilterProductWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[TaxWriteResource::class])) {
            $event->addEvent(TaxWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ProductManufacturerWriteResource::class])) {
            $event->addEvent(ProductManufacturerWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[FilterWriteResource::class])) {
            $event->addEvent(FilterWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ProductTranslationWriteResource::class])) {
            $event->addEvent(ProductTranslationWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ProductAccessoryWriteResource::class])) {
            $event->addEvent(ProductAccessoryWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ProductAttachmentWriteResource::class])) {
            $event->addEvent(ProductAttachmentWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ProductAvoidCustomerGroupWriteResource::class])) {
            $event->addEvent(ProductAvoidCustomerGroupWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ProductCategoryWriteResource::class])) {
            $event->addEvent(ProductCategoryWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ProductCategorySeoWriteResource::class])) {
            $event->addEvent(ProductCategorySeoWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ProductDetailWriteResource::class])) {
            $event->addEvent(ProductDetailWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ProductEsdWriteResource::class])) {
            $event->addEvent(ProductEsdWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ProductLinkWriteResource::class])) {
            $event->addEvent(ProductLinkWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ProductMediaWriteResource::class])) {
            $event->addEvent(ProductMediaWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ProductSimilarWriteResource::class])) {
            $event->addEvent(ProductSimilarWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ProductStreamAssignmentWriteResource::class])) {
            $event->addEvent(ProductStreamAssignmentWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ProductStreamTabWriteResource::class])) {
            $event->addEvent(ProductStreamTabWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ProductVoteWriteResource::class])) {
            $event->addEvent(ProductVoteWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[StatisticProductImpressionWriteResource::class])) {
            $event->addEvent(StatisticProductImpressionWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
