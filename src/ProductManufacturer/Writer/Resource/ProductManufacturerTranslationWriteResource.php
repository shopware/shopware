<?php declare(strict_types=1);

namespace Shopware\ProductManufacturer\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class ProductManufacturerTranslationWriteResource extends WriteResource
{
    protected const NAME_FIELD = 'name';
    protected const DESCRIPTION_FIELD = 'description';
    protected const META_TITLE_FIELD = 'metaTitle';
    protected const META_DESCRIPTION_FIELD = 'metaDescription';
    protected const META_KEYWORDS_FIELD = 'metaKeywords';

    public function __construct()
    {
        parent::__construct('product_manufacturer_translation');

        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::DESCRIPTION_FIELD] = new LongTextField('description');
        $this->fields[self::META_TITLE_FIELD] = new StringField('meta_title');
        $this->fields[self::META_DESCRIPTION_FIELD] = new StringField('meta_description');
        $this->fields[self::META_KEYWORDS_FIELD] = new StringField('meta_keywords');
        $this->fields['productManufacturer'] = new ReferenceField('productManufacturerUuid', 'uuid', \Shopware\ProductManufacturer\Writer\Resource\ProductManufacturerWriteResource::class);
        $this->primaryKeyFields['productManufacturerUuid'] = (new FkField('product_manufacturer_uuid', \Shopware\ProductManufacturer\Writer\Resource\ProductManufacturerWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\ProductManufacturer\Writer\Resource\ProductManufacturerWriteResource::class,
            \Shopware\Shop\Writer\Resource\ShopWriteResource::class,
            \Shopware\ProductManufacturer\Writer\Resource\ProductManufacturerTranslationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\ProductManufacturer\Event\ProductManufacturerTranslationWrittenEvent
    {
        $event = new \Shopware\ProductManufacturer\Event\ProductManufacturerTranslationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\ProductManufacturer\Writer\Resource\ProductManufacturerWriteResource::class])) {
            $event->addEvent(\Shopware\ProductManufacturer\Writer\Resource\ProductManufacturerWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopWriteResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\ProductManufacturer\Writer\Resource\ProductManufacturerTranslationWriteResource::class])) {
            $event->addEvent(\Shopware\ProductManufacturer\Writer\Resource\ProductManufacturerTranslationWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
