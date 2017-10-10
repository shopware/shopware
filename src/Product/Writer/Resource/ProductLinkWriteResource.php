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
use Shopware\Product\Event\ProductLinkWrittenEvent;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

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
        $this->fields['product'] = new ReferenceField('productUuid', 'uuid', ProductWriteResource::class);
        $this->fields['productUuid'] = (new FkField('product_uuid', ProductWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields[self::DESCRIPTION_FIELD] = new TranslatedField('description', ShopWriteResource::class, 'uuid');
        $this->fields[self::LINK_FIELD] = new TranslatedField('link', ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(ProductLinkTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            ProductWriteResource::class,
            self::class,
            ProductLinkTranslationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ProductLinkWrittenEvent
    {
        $event = new ProductLinkWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[ProductWriteResource::class])) {
            $event->addEvent(ProductWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ProductLinkTranslationWriteResource::class])) {
            $event->addEvent(ProductLinkTranslationWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
