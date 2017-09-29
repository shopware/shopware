<?php declare(strict_types=1);

namespace Shopware\Category\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class CategoryAvoidCustomerGroupWriteResource extends WriteResource
{
    public function __construct()
    {
        parent::__construct('category_avoid_customer_group');

        $this->fields['category'] = new ReferenceField('categoryUuid', 'uuid', \Shopware\Category\Writer\Resource\CategoryWriteResource::class);
        $this->fields['categoryUuid'] = (new FkField('category_uuid', \Shopware\Category\Writer\Resource\CategoryWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['customerGroup'] = new ReferenceField('customerGroupUuid', 'uuid', \Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource::class);
        $this->fields['customerGroupUuid'] = (new FkField('customer_group_uuid', \Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Category\Writer\Resource\CategoryWriteResource::class,
            \Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource::class,
            \Shopware\Category\Writer\Resource\CategoryAvoidCustomerGroupWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Category\Event\CategoryAvoidCustomerGroupWrittenEvent
    {
        $event = new \Shopware\Category\Event\CategoryAvoidCustomerGroupWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Category\Writer\Resource\CategoryWriteResource::class])) {
            $event->addEvent(\Shopware\Category\Writer\Resource\CategoryWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource::class])) {
            $event->addEvent(\Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Category\Writer\Resource\CategoryAvoidCustomerGroupWriteResource::class])) {
            $event->addEvent(\Shopware\Category\Writer\Resource\CategoryAvoidCustomerGroupWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
