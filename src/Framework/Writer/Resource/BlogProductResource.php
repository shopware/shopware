<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class BlogProductResource extends Resource
{
    public function __construct()
    {
        parent::__construct('blog_product');

        $this->fields['blog'] = new ReferenceField('blogUuid', 'uuid', \Shopware\Framework\Write\Resource\BlogResource::class);
        $this->fields['blogUuid'] = (new FkField('blog_uuid', \Shopware\Framework\Write\Resource\BlogResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['product'] = new ReferenceField('productUuid', 'uuid', \Shopware\Product\Writer\Resource\ProductResource::class);
        $this->fields['productUuid'] = (new FkField('product_uuid', \Shopware\Product\Writer\Resource\ProductResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\BlogResource::class,
            \Shopware\Product\Writer\Resource\ProductResource::class,
            \Shopware\Framework\Write\Resource\BlogProductResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Framework\Event\BlogProductWrittenEvent
    {
        $event = new \Shopware\Framework\Event\BlogProductWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\BlogResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\BlogResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\BlogProductResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\BlogProductResource::createWrittenEvent($updates));
        }

        return $event;
    }
}
