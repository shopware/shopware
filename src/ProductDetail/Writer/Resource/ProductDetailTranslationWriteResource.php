<?php declare(strict_types=1);

namespace Shopware\ProductDetail\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\ProductDetail\Event\ProductDetailTranslationWrittenEvent;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

class ProductDetailTranslationWriteResource extends WriteResource
{
    protected const ADDITIONAL_TEXT_FIELD = 'additionalText';
    protected const PACK_UNIT_FIELD = 'packUnit';

    public function __construct()
    {
        parent::__construct('product_detail_translation');

        $this->fields[self::ADDITIONAL_TEXT_FIELD] = new StringField('additional_text');
        $this->fields[self::PACK_UNIT_FIELD] = new StringField('pack_unit');
        $this->fields['productDetail'] = new ReferenceField('productDetailUuid', 'uuid', ProductDetailWriteResource::class);
        $this->primaryKeyFields['productDetailUuid'] = (new FkField('product_detail_uuid', ProductDetailWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', ShopWriteResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', ShopWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            ProductDetailWriteResource::class,
            ShopWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ProductDetailTranslationWrittenEvent
    {
        $event = new ProductDetailTranslationWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[ProductDetailWriteResource::class])) {
            $event->addEvent(ProductDetailWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ShopWriteResource::class])) {
            $event->addEvent(ShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
