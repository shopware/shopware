<?php declare(strict_types=1);

namespace Shopware\ProductManufacturer\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\Product\Writer\Resource\ProductWriteResource;
use Shopware\ProductManufacturer\Event\ProductManufacturerWrittenEvent;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

class ProductManufacturerWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const LINK_FIELD = 'link';
    protected const MEDIA_UUID_FIELD = 'mediaUuid';
    protected const NAME_FIELD = 'name';
    protected const DESCRIPTION_FIELD = 'description';
    protected const META_TITLE_FIELD = 'metaTitle';
    protected const META_DESCRIPTION_FIELD = 'metaDescription';
    protected const META_KEYWORDS_FIELD = 'metaKeywords';

    public function __construct()
    {
        parent::__construct('product_manufacturer');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::LINK_FIELD] = (new StringField('link'))->setFlags(new Required());
        $this->fields[self::MEDIA_UUID_FIELD] = new StringField('media_uuid');
        $this->fields['products'] = new SubresourceField(ProductWriteResource::class);
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', ShopWriteResource::class, 'uuid');
        $this->fields[self::DESCRIPTION_FIELD] = new TranslatedField('description', ShopWriteResource::class, 'uuid');
        $this->fields[self::META_TITLE_FIELD] = new TranslatedField('metaTitle', ShopWriteResource::class, 'uuid');
        $this->fields[self::META_DESCRIPTION_FIELD] = new TranslatedField('metaDescription', ShopWriteResource::class, 'uuid');
        $this->fields[self::META_KEYWORDS_FIELD] = new TranslatedField('metaKeywords', ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(ProductManufacturerTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            ProductWriteResource::class,
            self::class,
            ProductManufacturerTranslationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ProductManufacturerWrittenEvent
    {
        $event = new ProductManufacturerWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[ProductWriteResource::class])) {
            $event->addEvent(ProductWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ProductManufacturerTranslationWriteResource::class])) {
            $event->addEvent(ProductManufacturerTranslationWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
