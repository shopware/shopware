<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class ProductLinkWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const TARGET_FIELD = 'target';
    protected const DESCRIPTION_FIELD = 'description';
    protected const LINK_FIELD = 'link';

    public function __construct()
    {
        parent::__construct('product_link');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::TARGET_FIELD] = new StringField('target');
        $this->fields['product'] = new ReferenceField('productUuid', 'uuid', \Shopware\Product\Writer\Resource\ProductWriteResource::class);
        $this->fields['productUuid'] = (new FkField('product_uuid', \Shopware\Product\Writer\Resource\ProductWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields[self::DESCRIPTION_FIELD] = new TranslatedField('description', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields[self::LINK_FIELD] = new TranslatedField('link', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\Product\Writer\Resource\ProductLinkTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductWriteResource::class,
            \Shopware\Product\Writer\Resource\ProductLinkWriteResource::class,
            \Shopware\Product\Writer\Resource\ProductLinkTranslationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Product\Event\ProductLinkWrittenEvent
    {
        $event = new \Shopware\Product\Event\ProductLinkWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductWriteResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductLinkWriteResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductLinkWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductLinkTranslationWriteResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductLinkTranslationWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
