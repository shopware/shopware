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
use Shopware\Framework\Write\WriteResource;

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
        $this->fields['blogProducts'] = new SubresourceField(\Shopware\Framework\Write\Resource\BlogProductWriteResource::class);
        $this->fields['filterProducts'] = new SubresourceField(\Shopware\Framework\Write\Resource\FilterProductWriteResource::class);
        $this->fields['tax'] = new ReferenceField('taxUuid', 'uuid', \Shopware\Tax\Writer\Resource\TaxWriteResource::class);
        $this->fields['taxUuid'] = (new FkField('tax_uuid', \Shopware\Tax\Writer\Resource\TaxWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['manufacturer'] = new ReferenceField('manufacturerUuid', 'uuid', \Shopware\ProductManufacturer\Writer\Resource\ProductManufacturerWriteResource::class);
        $this->fields['manufacturerUuid'] = (new FkField('product_manufacturer_uuid', \Shopware\ProductManufacturer\Writer\Resource\ProductManufacturerWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['filterGroup'] = new ReferenceField('filterGroupUuid', 'uuid', \Shopware\Framework\Write\Resource\FilterWriteResource::class);
        $this->fields['filterGroupUuid'] = (new FkField('filter_group_uuid', \Shopware\Framework\Write\Resource\FilterWriteResource::class, 'uuid'));
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields[self::KEYWORDS_FIELD] = new TranslatedField('keywords', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields[self::DESCRIPTION_FIELD] = new TranslatedField('description', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields[self::DESCRIPTION_LONG_FIELD] = new TranslatedField('descriptionLong', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields[self::META_TITLE_FIELD] = new TranslatedField('metaTitle', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\Product\Writer\Resource\ProductTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['accessories'] = new SubresourceField(\Shopware\Product\Writer\Resource\ProductAccessoryWriteResource::class);
        $this->fields['attachments'] = new SubresourceField(\Shopware\Product\Writer\Resource\ProductAttachmentWriteResource::class);
        $this->fields['avoidCustomerGroups'] = new SubresourceField(\Shopware\Product\Writer\Resource\ProductAvoidCustomerGroupWriteResource::class);
        $this->fields['categories'] = new SubresourceField(\Shopware\Product\Writer\Resource\ProductCategoryWriteResource::class);
        $this->fields['categorySeos'] = new SubresourceField(\Shopware\Product\Writer\Resource\ProductCategorySeoWriteResource::class);
        $this->fields['details'] = new SubresourceField(\Shopware\ProductDetail\Writer\Resource\ProductDetailWriteResource::class);
        $this->fields['esds'] = new SubresourceField(\Shopware\Product\Writer\Resource\ProductEsdWriteResource::class);
        $this->fields['links'] = new SubresourceField(\Shopware\Product\Writer\Resource\ProductLinkWriteResource::class);
        $this->fields['media'] = new SubresourceField(\Shopware\ProductMedia\Writer\Resource\ProductMediaWriteResource::class);
        $this->fields['similars'] = new SubresourceField(\Shopware\Product\Writer\Resource\ProductSimilarWriteResource::class);
        $this->fields['streamAssignments'] = new SubresourceField(\Shopware\ProductStream\Writer\Resource\ProductStreamAssignmentWriteResource::class);
        $this->fields['streamTabs'] = new SubresourceField(\Shopware\ProductStream\Writer\Resource\ProductStreamTabWriteResource::class);
        $this->fields['votes'] = new SubresourceField(\Shopware\ProductVote\Writer\Resource\ProductVoteWriteResource::class);
        $this->fields['statisticProductImpressions'] = new SubresourceField(\Shopware\Framework\Write\Resource\StatisticProductImpressionWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\BlogProductWriteResource::class,
            \Shopware\Framework\Write\Resource\FilterProductWriteResource::class,
            \Shopware\Tax\Writer\Resource\TaxWriteResource::class,
            \Shopware\ProductManufacturer\Writer\Resource\ProductManufacturerWriteResource::class,
            \Shopware\Framework\Write\Resource\FilterWriteResource::class,
            \Shopware\Product\Writer\Resource\ProductWriteResource::class,
            \Shopware\Product\Writer\Resource\ProductTranslationWriteResource::class,
            \Shopware\Product\Writer\Resource\ProductAccessoryWriteResource::class,
            \Shopware\Product\Writer\Resource\ProductAttachmentWriteResource::class,
            \Shopware\Product\Writer\Resource\ProductAvoidCustomerGroupWriteResource::class,
            \Shopware\Product\Writer\Resource\ProductCategoryWriteResource::class,
            \Shopware\Product\Writer\Resource\ProductCategorySeoWriteResource::class,
            \Shopware\ProductDetail\Writer\Resource\ProductDetailWriteResource::class,
            \Shopware\Product\Writer\Resource\ProductEsdWriteResource::class,
            \Shopware\Product\Writer\Resource\ProductLinkWriteResource::class,
            \Shopware\ProductMedia\Writer\Resource\ProductMediaWriteResource::class,
            \Shopware\Product\Writer\Resource\ProductSimilarWriteResource::class,
            \Shopware\ProductStream\Writer\Resource\ProductStreamAssignmentWriteResource::class,
            \Shopware\ProductStream\Writer\Resource\ProductStreamTabWriteResource::class,
            \Shopware\ProductVote\Writer\Resource\ProductVoteWriteResource::class,
            \Shopware\Framework\Write\Resource\StatisticProductImpressionWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Product\Event\ProductWrittenEvent
    {
        $event = new \Shopware\Product\Event\ProductWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\BlogProductWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\BlogProductWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterProductWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterProductWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Tax\Writer\Resource\TaxWriteResource::class])) {
            $event->addEvent(\Shopware\Tax\Writer\Resource\TaxWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\ProductManufacturer\Writer\Resource\ProductManufacturerWriteResource::class])) {
            $event->addEvent(\Shopware\ProductManufacturer\Writer\Resource\ProductManufacturerWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductWriteResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductTranslationWriteResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductTranslationWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductAccessoryWriteResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductAccessoryWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductAttachmentWriteResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductAttachmentWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductAvoidCustomerGroupWriteResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductAvoidCustomerGroupWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductCategoryWriteResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductCategoryWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductCategorySeoWriteResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductCategorySeoWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\ProductDetail\Writer\Resource\ProductDetailWriteResource::class])) {
            $event->addEvent(\Shopware\ProductDetail\Writer\Resource\ProductDetailWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductEsdWriteResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductEsdWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductLinkWriteResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductLinkWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\ProductMedia\Writer\Resource\ProductMediaWriteResource::class])) {
            $event->addEvent(\Shopware\ProductMedia\Writer\Resource\ProductMediaWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductSimilarWriteResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductSimilarWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\ProductStream\Writer\Resource\ProductStreamAssignmentWriteResource::class])) {
            $event->addEvent(\Shopware\ProductStream\Writer\Resource\ProductStreamAssignmentWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\ProductStream\Writer\Resource\ProductStreamTabWriteResource::class])) {
            $event->addEvent(\Shopware\ProductStream\Writer\Resource\ProductStreamTabWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\ProductVote\Writer\Resource\ProductVoteWriteResource::class])) {
            $event->addEvent(\Shopware\ProductVote\Writer\Resource\ProductVoteWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\StatisticProductImpressionWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\StatisticProductImpressionWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
