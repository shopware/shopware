<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Category\Writer\Resource\CategoryWriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\Product\Event\ProductCategoryWrittenEvent;

class ProductCategoryWriteResource extends WriteResource
{
    public function __construct()
    {
        parent::__construct('product_category');

        $this->fields['product'] = new ReferenceField('productUuid', 'uuid', ProductWriteResource::class);
        $this->primaryKeyFields['productUuid'] = (new FkField('product_uuid', ProductWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['category'] = new ReferenceField('categoryUuid', 'uuid', CategoryWriteResource::class);
        $this->primaryKeyFields['categoryUuid'] = (new FkField('category_uuid', CategoryWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            ProductWriteResource::class,
            CategoryWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ProductCategoryWrittenEvent
    {
        $event = new ProductCategoryWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[ProductWriteResource::class])) {
            $event->addEvent(ProductWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[CategoryWriteResource::class])) {
            $event->addEvent(CategoryWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
