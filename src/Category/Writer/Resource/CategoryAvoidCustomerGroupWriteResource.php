<?php declare(strict_types=1);

namespace Shopware\Category\Writer\Resource;

use Shopware\Category\Event\CategoryAvoidCustomerGroupWrittenEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class CategoryAvoidCustomerGroupWriteResource extends WriteResource
{
    public function __construct()
    {
        parent::__construct('category_avoid_customer_group');

        $this->fields['category'] = new ReferenceField('categoryUuid', 'uuid', CategoryWriteResource::class);
        $this->fields['categoryUuid'] = (new FkField('category_uuid', CategoryWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['customerGroup'] = new ReferenceField('customerGroupUuid', 'uuid', CustomerGroupWriteResource::class);
        $this->fields['customerGroupUuid'] = (new FkField('customer_group_uuid', CustomerGroupWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            CategoryWriteResource::class,
            CustomerGroupWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): CategoryAvoidCustomerGroupWrittenEvent
    {
        $event = new CategoryAvoidCustomerGroupWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[CategoryWriteResource::class])) {
            $event->addEvent(CategoryWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[CustomerGroupWriteResource::class])) {
            $event->addEvent(CustomerGroupWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
