<?php declare(strict_types=1);

namespace Shopware\ShippingMethod\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class ShippingMethodCategoryWriteResource extends WriteResource
{
    public function __construct()
    {
        parent::__construct('shipping_method_category');

        $this->fields['shippingMethod'] = new ReferenceField('shippingMethodUuid', 'uuid', \Shopware\ShippingMethod\Writer\Resource\ShippingMethodWriteResource::class);
        $this->fields['shippingMethodUuid'] = (new FkField('shipping_method_uuid', \Shopware\ShippingMethod\Writer\Resource\ShippingMethodWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['category'] = new ReferenceField('categoryUuid', 'uuid', \Shopware\Category\Writer\Resource\CategoryWriteResource::class);
        $this->fields['categoryUuid'] = (new FkField('category_uuid', \Shopware\Category\Writer\Resource\CategoryWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\ShippingMethod\Writer\Resource\ShippingMethodWriteResource::class,
            \Shopware\Category\Writer\Resource\CategoryWriteResource::class,
            \Shopware\ShippingMethod\Writer\Resource\ShippingMethodCategoryWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\ShippingMethod\Event\ShippingMethodCategoryWrittenEvent
    {
        $event = new \Shopware\ShippingMethod\Event\ShippingMethodCategoryWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\ShippingMethod\Writer\Resource\ShippingMethodWriteResource::class])) {
            $event->addEvent(\Shopware\ShippingMethod\Writer\Resource\ShippingMethodWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Category\Writer\Resource\CategoryWriteResource::class])) {
            $event->addEvent(\Shopware\Category\Writer\Resource\CategoryWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\ShippingMethod\Writer\Resource\ShippingMethodCategoryWriteResource::class])) {
            $event->addEvent(\Shopware\ShippingMethod\Writer\Resource\ShippingMethodCategoryWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
