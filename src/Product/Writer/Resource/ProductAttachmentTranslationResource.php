<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ProductAttachmentTranslationResource extends Resource
{
    protected const DESCRIPTION_FIELD = 'description';

    public function __construct()
    {
        parent::__construct('product_attachment_translation');

        $this->fields[self::DESCRIPTION_FIELD] = (new StringField('description'))->setFlags(new Required());
        $this->fields['productAttachment'] = new ReferenceField('productAttachmentUuid', 'uuid', \Shopware\Product\Writer\Resource\ProductAttachmentResource::class);
        $this->primaryKeyFields['productAttachmentUuid'] = (new FkField('product_attachment_uuid', \Shopware\Product\Writer\Resource\ProductAttachmentResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductAttachmentResource::class,
            \Shopware\Shop\Writer\Resource\ShopResource::class,
            \Shopware\Product\Writer\Resource\ProductAttachmentTranslationResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Product\Event\ProductAttachmentTranslationWrittenEvent
    {
        $event = new \Shopware\Product\Event\ProductAttachmentTranslationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductAttachmentResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductAttachmentResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductAttachmentTranslationResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductAttachmentTranslationResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
