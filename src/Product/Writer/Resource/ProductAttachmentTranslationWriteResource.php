<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class ProductAttachmentTranslationWriteResource extends WriteResource
{
    protected const DESCRIPTION_FIELD = 'description';

    public function __construct()
    {
        parent::__construct('product_attachment_translation');

        $this->fields[self::DESCRIPTION_FIELD] = (new StringField('description'))->setFlags(new Required());
        $this->fields['productAttachment'] = new ReferenceField('productAttachmentUuid', 'uuid', \Shopware\Product\Writer\Resource\ProductAttachmentWriteResource::class);
        $this->primaryKeyFields['productAttachmentUuid'] = (new FkField('product_attachment_uuid', \Shopware\Product\Writer\Resource\ProductAttachmentWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductAttachmentWriteResource::class,
            \Shopware\Shop\Writer\Resource\ShopWriteResource::class,
            \Shopware\Product\Writer\Resource\ProductAttachmentTranslationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Product\Event\ProductAttachmentTranslationWrittenEvent
    {
        $event = new \Shopware\Product\Event\ProductAttachmentTranslationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductAttachmentWriteResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductAttachmentWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopWriteResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductAttachmentTranslationWriteResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductAttachmentTranslationWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
