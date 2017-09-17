<?php declare(strict_types=1);

namespace Shopware\ProductDetail\Writer\Resource;

use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ProductDetailTranslationResource extends Resource
{
    protected const ADDITIONAL_TEXT_FIELD = 'additionalText';
    protected const PACK_UNIT_FIELD = 'packUnit';

    public function __construct()
    {
        parent::__construct('product_detail_translation');

        $this->fields[self::ADDITIONAL_TEXT_FIELD] = new StringField('additional_text');
        $this->fields[self::PACK_UNIT_FIELD] = new StringField('pack_unit');
        $this->fields['productDetail'] = new ReferenceField('productDetailUuid', 'uuid', \Shopware\ProductDetail\Writer\Resource\ProductDetailResource::class);
        $this->primaryKeyFields['productDetailUuid'] = (new FkField('product_detail_uuid', \Shopware\ProductDetail\Writer\Resource\ProductDetailResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\ProductDetail\Writer\Resource\ProductDetailResource::class,
            \Shopware\Shop\Writer\Resource\ShopResource::class,
            \Shopware\ProductDetail\Writer\Resource\ProductDetailTranslationResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\ProductDetail\Event\ProductDetailTranslationWrittenEvent
    {
        $event = new \Shopware\ProductDetail\Event\ProductDetailTranslationWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\ProductDetail\Writer\Resource\ProductDetailResource::class])) {
            $event->addEvent(\Shopware\ProductDetail\Writer\Resource\ProductDetailResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\ProductDetail\Writer\Resource\ProductDetailTranslationResource::class])) {
            $event->addEvent(\Shopware\ProductDetail\Writer\Resource\ProductDetailTranslationResource::createWrittenEvent($updates));
        }

        return $event;
    }
}
