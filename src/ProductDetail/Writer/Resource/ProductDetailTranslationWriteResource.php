<?php declare(strict_types=1);

namespace Shopware\ProductDetail\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class ProductDetailTranslationWriteResource extends WriteResource
{
    protected const ADDITIONAL_TEXT_FIELD = 'additionalText';
    protected const PACK_UNIT_FIELD = 'packUnit';

    public function __construct()
    {
        parent::__construct('product_detail_translation');

        $this->fields[self::ADDITIONAL_TEXT_FIELD] = new StringField('additional_text');
        $this->fields[self::PACK_UNIT_FIELD] = new StringField('pack_unit');
        $this->fields['productDetail'] = new ReferenceField('productDetailUuid', 'uuid', \Shopware\ProductDetail\Writer\Resource\ProductDetailWriteResource::class);
        $this->primaryKeyFields['productDetailUuid'] = (new FkField('product_detail_uuid', \Shopware\ProductDetail\Writer\Resource\ProductDetailWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\ProductDetail\Writer\Resource\ProductDetailWriteResource::class,
            \Shopware\Shop\Writer\Resource\ShopWriteResource::class,
            \Shopware\ProductDetail\Writer\Resource\ProductDetailTranslationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\ProductDetail\Event\ProductDetailTranslationWrittenEvent
    {
        $event = new \Shopware\ProductDetail\Event\ProductDetailTranslationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\ProductDetail\Writer\Resource\ProductDetailWriteResource::class])) {
            $event->addEvent(\Shopware\ProductDetail\Writer\Resource\ProductDetailWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopWriteResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\ProductDetail\Writer\Resource\ProductDetailTranslationWriteResource::class])) {
            $event->addEvent(\Shopware\ProductDetail\Writer\Resource\ProductDetailTranslationWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
