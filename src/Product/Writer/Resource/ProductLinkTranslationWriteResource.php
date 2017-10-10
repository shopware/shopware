<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\Product\Event\ProductLinkTranslationWrittenEvent;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

class ProductLinkTranslationWriteResource extends WriteResource
{
    protected const DESCRIPTION_FIELD = 'description';
    protected const LINK_FIELD = 'link';

    public function __construct()
    {
        parent::__construct('product_link_translation');

        $this->fields[self::DESCRIPTION_FIELD] = (new StringField('description'))->setFlags(new Required());
        $this->fields[self::LINK_FIELD] = (new StringField('link'))->setFlags(new Required());
        $this->fields['productLink'] = new ReferenceField('productLinkUuid', 'uuid', ProductLinkWriteResource::class);
        $this->primaryKeyFields['productLinkUuid'] = (new FkField('product_link_uuid', ProductLinkWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', ShopWriteResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', ShopWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            ProductLinkWriteResource::class,
            ShopWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ProductLinkTranslationWrittenEvent
    {
        $event = new ProductLinkTranslationWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[ProductLinkWriteResource::class])) {
            $event->addEvent(ProductLinkWriteResource::createWrittenEvent($updates, $context));
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
