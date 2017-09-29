<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class ProductAvoidCustomerGroupWriteResource extends WriteResource
{
    public function __construct()
    {
        parent::__construct('product_avoid_customer_group');

        $this->fields['product'] = new ReferenceField('productUuid', 'uuid', \Shopware\Product\Writer\Resource\ProductWriteResource::class);
        $this->fields['productUuid'] = (new FkField('product_uuid', \Shopware\Product\Writer\Resource\ProductWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['customerGroup'] = new ReferenceField('customerGroupUuid', 'uuid', \Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource::class);
        $this->fields['customerGroupUuid'] = (new FkField('customer_group_uuid', \Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductWriteResource::class,
            \Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource::class,
            \Shopware\Product\Writer\Resource\ProductAvoidCustomerGroupWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Product\Event\ProductAvoidCustomerGroupWrittenEvent
    {
        $event = new \Shopware\Product\Event\ProductAvoidCustomerGroupWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductWriteResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource::class])) {
            $event->addEvent(\Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductAvoidCustomerGroupWriteResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductAvoidCustomerGroupWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
