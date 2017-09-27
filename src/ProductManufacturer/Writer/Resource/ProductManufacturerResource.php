<?php declare(strict_types=1);

namespace Shopware\ProductManufacturer\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ProductManufacturerResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const LINK_FIELD = 'link';
    protected const MEDIA_UUID_FIELD = 'mediaUuid';
    protected const UPDATED_AT_FIELD = 'updatedAt';
    protected const CREATED_AT_FIELD = 'createdAt';
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
        $this->fields[self::UPDATED_AT_FIELD] = (new DateField('updated_at'))->setFlags(new Required());
        $this->fields[self::CREATED_AT_FIELD] = new DateField('created_at');
        $this->fields['products'] = new SubresourceField(\Shopware\Product\Writer\Resource\ProductResource::class);
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields[self::DESCRIPTION_FIELD] = new TranslatedField('description', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields[self::META_TITLE_FIELD] = new TranslatedField('metaTitle', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields[self::META_DESCRIPTION_FIELD] = new TranslatedField('metaDescription', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields[self::META_KEYWORDS_FIELD] = new TranslatedField('metaKeywords', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\ProductManufacturer\Writer\Resource\ProductManufacturerTranslationResource::class, 'languageUuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductResource::class,
            \Shopware\ProductManufacturer\Writer\Resource\ProductManufacturerResource::class,
            \Shopware\ProductManufacturer\Writer\Resource\ProductManufacturerTranslationResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\ProductManufacturer\Event\ProductManufacturerWrittenEvent
    {
        $event = new \Shopware\ProductManufacturer\Event\ProductManufacturerWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\ProductManufacturer\Writer\Resource\ProductManufacturerResource::class])) {
            $event->addEvent(\Shopware\ProductManufacturer\Writer\Resource\ProductManufacturerResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\ProductManufacturer\Writer\Resource\ProductManufacturerTranslationResource::class])) {
            $event->addEvent(\Shopware\ProductManufacturer\Writer\Resource\ProductManufacturerTranslationResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }

    public function getDefaults(string $type): array
    {
        if (self::FOR_UPDATE === $type) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
            ];
        }

        if (self::FOR_INSERT === $type) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
                self::CREATED_AT_FIELD => new \DateTime(),
            ];
        }

        throw new \InvalidArgumentException('Unable to generate default values, wrong type submitted');
    }
}
