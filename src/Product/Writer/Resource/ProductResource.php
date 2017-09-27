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
use Shopware\Framework\Write\Resource;

class ProductResource extends Resource
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
        $this->fields['blogProducts'] = new SubresourceField(\Shopware\Framework\Write\Resource\BlogProductResource::class);
        $this->fields['filterProducts'] = new SubresourceField(\Shopware\Framework\Write\Resource\FilterProductResource::class);
        $this->fields['tax'] = new ReferenceField('taxUuid', 'uuid', \Shopware\Tax\Writer\Resource\TaxResource::class);
        $this->fields['taxUuid'] = (new FkField('tax_uuid', \Shopware\Tax\Writer\Resource\TaxResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['manufacturer'] = new ReferenceField('manufacturerUuid', 'uuid', \Shopware\ProductManufacturer\Writer\Resource\ProductManufacturerResource::class);
        $this->fields['manufacturerUuid'] = (new FkField('product_manufacturer_uuid', \Shopware\ProductManufacturer\Writer\Resource\ProductManufacturerResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['filterGroup'] = new ReferenceField('filterGroupUuid', 'uuid', \Shopware\Framework\Write\Resource\FilterResource::class);
        $this->fields['filterGroupUuid'] = (new FkField('filter_group_uuid', \Shopware\Framework\Write\Resource\FilterResource::class, 'uuid'));
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields[self::KEYWORDS_FIELD] = new TranslatedField('keywords', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields[self::DESCRIPTION_FIELD] = new TranslatedField('description', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields[self::DESCRIPTION_LONG_FIELD] = new TranslatedField('descriptionLong', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields[self::META_TITLE_FIELD] = new TranslatedField('metaTitle', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\Product\Writer\Resource\ProductTranslationResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['accessories'] = new SubresourceField(\Shopware\Product\Writer\Resource\ProductAccessoryResource::class);
        $this->fields['attachments'] = new SubresourceField(\Shopware\Product\Writer\Resource\ProductAttachmentResource::class);
        $this->fields['avoidCustomerGroups'] = new SubresourceField(\Shopware\Product\Writer\Resource\ProductAvoidCustomerGroupResource::class);
        $this->fields['categories'] = new SubresourceField(\Shopware\Product\Writer\Resource\ProductCategoryResource::class);
        $this->fields['categorySeos'] = new SubresourceField(\Shopware\Product\Writer\Resource\ProductCategorySeoResource::class);
        $this->fields['details'] = new SubresourceField(\Shopware\ProductDetail\Writer\Resource\ProductDetailResource::class);
        $this->fields['esds'] = new SubresourceField(\Shopware\Product\Writer\Resource\ProductEsdResource::class);
        $this->fields['links'] = new SubresourceField(\Shopware\Product\Writer\Resource\ProductLinkResource::class);
        $this->fields['media'] = new SubresourceField(\Shopware\ProductMedia\Writer\Resource\ProductMediaResource::class);
        $this->fields['similars'] = new SubresourceField(\Shopware\Product\Writer\Resource\ProductSimilarResource::class);
        $this->fields['streamAssignments'] = new SubresourceField(\Shopware\ProductStream\Writer\Resource\ProductStreamAssignmentResource::class);
        $this->fields['streamTabs'] = new SubresourceField(\Shopware\ProductStream\Writer\Resource\ProductStreamTabResource::class);
        $this->fields['votes'] = new SubresourceField(\Shopware\ProductVote\Writer\Resource\ProductVoteResource::class);
        $this->fields['statisticProductImpressions'] = new SubresourceField(\Shopware\Framework\Write\Resource\StatisticProductImpressionResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\BlogProductResource::class,
            \Shopware\Framework\Write\Resource\FilterProductResource::class,
            \Shopware\Tax\Writer\Resource\TaxResource::class,
            \Shopware\ProductManufacturer\Writer\Resource\ProductManufacturerResource::class,
            \Shopware\Framework\Write\Resource\FilterResource::class,
            \Shopware\Product\Writer\Resource\ProductResource::class,
            \Shopware\Product\Writer\Resource\ProductTranslationResource::class,
            \Shopware\Product\Writer\Resource\ProductAccessoryResource::class,
            \Shopware\Product\Writer\Resource\ProductAttachmentResource::class,
            \Shopware\Product\Writer\Resource\ProductAvoidCustomerGroupResource::class,
            \Shopware\Product\Writer\Resource\ProductCategoryResource::class,
            \Shopware\Product\Writer\Resource\ProductCategorySeoResource::class,
            \Shopware\ProductDetail\Writer\Resource\ProductDetailResource::class,
            \Shopware\Product\Writer\Resource\ProductEsdResource::class,
            \Shopware\Product\Writer\Resource\ProductLinkResource::class,
            \Shopware\ProductMedia\Writer\Resource\ProductMediaResource::class,
            \Shopware\Product\Writer\Resource\ProductSimilarResource::class,
            \Shopware\ProductStream\Writer\Resource\ProductStreamAssignmentResource::class,
            \Shopware\ProductStream\Writer\Resource\ProductStreamTabResource::class,
            \Shopware\ProductVote\Writer\Resource\ProductVoteResource::class,
            \Shopware\Framework\Write\Resource\StatisticProductImpressionResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Product\Event\ProductWrittenEvent
    {
        $event = new \Shopware\Product\Event\ProductWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\BlogProductResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\BlogProductResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterProductResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterProductResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Tax\Writer\Resource\TaxResource::class])) {
            $event->addEvent(\Shopware\Tax\Writer\Resource\TaxResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\ProductManufacturer\Writer\Resource\ProductManufacturerResource::class])) {
            $event->addEvent(\Shopware\ProductManufacturer\Writer\Resource\ProductManufacturerResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductTranslationResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductTranslationResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductAccessoryResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductAccessoryResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductAccessoryResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductAccessoryResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductAttachmentResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductAttachmentResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductAvoidCustomerGroupResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductAvoidCustomerGroupResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductCategoryResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductCategoryResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductCategorySeoResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductCategorySeoResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\ProductDetail\Writer\Resource\ProductDetailResource::class])) {
            $event->addEvent(\Shopware\ProductDetail\Writer\Resource\ProductDetailResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductEsdResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductEsdResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductLinkResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductLinkResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\ProductMedia\Writer\Resource\ProductMediaResource::class])) {
            $event->addEvent(\Shopware\ProductMedia\Writer\Resource\ProductMediaResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductSimilarResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductSimilarResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductSimilarResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductSimilarResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\ProductStream\Writer\Resource\ProductStreamAssignmentResource::class])) {
            $event->addEvent(\Shopware\ProductStream\Writer\Resource\ProductStreamAssignmentResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\ProductStream\Writer\Resource\ProductStreamTabResource::class])) {
            $event->addEvent(\Shopware\ProductStream\Writer\Resource\ProductStreamTabResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\ProductVote\Writer\Resource\ProductVoteResource::class])) {
            $event->addEvent(\Shopware\ProductVote\Writer\Resource\ProductVoteResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\StatisticProductImpressionResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\StatisticProductImpressionResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
