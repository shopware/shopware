<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class ProductLinkTranslationWriteResource extends WriteResource
{
    protected const DESCRIPTION_FIELD = 'description';
    protected const LINK_FIELD = 'link';

    public function __construct()
    {
        parent::__construct('product_link_translation');

        $this->fields[self::DESCRIPTION_FIELD] = (new StringField('description'))->setFlags(new Required());
        $this->fields[self::LINK_FIELD] = (new StringField('link'))->setFlags(new Required());
        $this->fields['productLink'] = new ReferenceField('productLinkUuid', 'uuid', \Shopware\Product\Writer\Resource\ProductLinkWriteResource::class);
        $this->primaryKeyFields['productLinkUuid'] = (new FkField('product_link_uuid', \Shopware\Product\Writer\Resource\ProductLinkWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductLinkWriteResource::class,
            \Shopware\Shop\Writer\Resource\ShopWriteResource::class,
            \Shopware\Product\Writer\Resource\ProductLinkTranslationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Product\Event\ProductLinkTranslationWrittenEvent
    {
        $event = new \Shopware\Product\Event\ProductLinkTranslationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductLinkWriteResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductLinkWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopWriteResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductLinkTranslationWriteResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductLinkTranslationWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
