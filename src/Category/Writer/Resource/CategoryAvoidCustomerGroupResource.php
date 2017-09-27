<?php declare(strict_types=1);

namespace Shopware\Category\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class CategoryAvoidCustomerGroupResource extends Resource
{
    protected const CREATED_AT_FIELD = 'createdAt';
    protected const UPDATED_AT_FIELD = 'updatedAt';

    public function __construct()
    {
        parent::__construct('category_avoid_customer_group');

        $this->fields[self::CREATED_AT_FIELD] = new DateField('created_at');
        $this->fields[self::UPDATED_AT_FIELD] = new DateField('updated_at');
        $this->fields['category'] = new ReferenceField('categoryUuid', 'uuid', \Shopware\Category\Writer\Resource\CategoryResource::class);
        $this->fields['categoryUuid'] = (new FkField('category_uuid', \Shopware\Category\Writer\Resource\CategoryResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['customerGroup'] = new ReferenceField('customerGroupUuid', 'uuid', \Shopware\CustomerGroup\Writer\Resource\CustomerGroupResource::class);
        $this->fields['customerGroupUuid'] = (new FkField('customer_group_uuid', \Shopware\CustomerGroup\Writer\Resource\CustomerGroupResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Category\Writer\Resource\CategoryResource::class,
            \Shopware\CustomerGroup\Writer\Resource\CustomerGroupResource::class,
            \Shopware\Category\Writer\Resource\CategoryAvoidCustomerGroupResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Category\Event\CategoryAvoidCustomerGroupWrittenEvent
    {
        $event = new \Shopware\Category\Event\CategoryAvoidCustomerGroupWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Category\Writer\Resource\CategoryResource::class])) {
            $event->addEvent(\Shopware\Category\Writer\Resource\CategoryResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\CustomerGroup\Writer\Resource\CustomerGroupResource::class])) {
            $event->addEvent(\Shopware\CustomerGroup\Writer\Resource\CustomerGroupResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Category\Writer\Resource\CategoryAvoidCustomerGroupResource::class])) {
            $event->addEvent(\Shopware\Category\Writer\Resource\CategoryAvoidCustomerGroupResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }

    public function getDefaults(string $type): array
    {
        if (self::FOR_UPDATE === $type) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
            ];
        }

        if (self::FOR_INSERT === $type) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
                self::CREATED_AT_FIELD => new \DateTime(),
            ];
        }

        throw new \InvalidArgumentException('Unable to generate default values, wrong type submitted');
    }
}
