<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ProductMediaTranslationResource extends Resource
{
    protected const DESCRIPTION_FIELD = 'description';

    public function __construct()
    {
        parent::__construct('product_media_translation');

        $this->fields[self::DESCRIPTION_FIELD] = (new StringField('description'))->setFlags(new Required());
        $this->fields['productMedia'] = new ReferenceField('productMediaUuid', 'uuid', \Shopware\Product\Writer\Resource\ProductMediaResource::class);
        $this->primaryKeyFields['productMediaUuid'] = (new FkField('product_media_uuid', \Shopware\Product\Writer\Resource\ProductMediaResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductMediaResource::class,
            \Shopware\Shop\Writer\Resource\ShopResource::class,
            \Shopware\Product\Writer\Resource\ProductMediaTranslationResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Product\Event\ProductMediaTranslationWrittenEvent
    {
        $event = new \Shopware\Product\Event\ProductMediaTranslationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductMediaResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductMediaResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductMediaTranslationResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductMediaTranslationResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
