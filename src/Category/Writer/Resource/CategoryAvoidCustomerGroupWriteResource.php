<?php declare(strict_types=1);

namespace Shopware\Category\Writer\Resource;

use Shopware\Api\Write\Field\FkField;
use Shopware\Api\Write\Field\ReferenceField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Category\Event\CategoryAvoidCustomerGroupWrittenEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource;

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

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): CategoryAvoidCustomerGroupWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new CategoryAvoidCustomerGroupWrittenEvent($uuids, $context, $rawData, $errors);

        unset($updates[self::class]);

        /**
         * @var WriteResource
         * @var string[]      $identifiers
         */
        foreach ($updates as $class => $identifiers) {
            if (!array_key_exists($class, $updates) || count($updates[$class]) === 0) {
                continue;
            }

            $event->addEvent($class::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
