<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ProductConfiguratorDependencyResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const CONFIGURATOR_SET_ID_FIELD = 'configuratorSetId';
    protected const PARENT_ID_FIELD = 'parentId';
    protected const CHILD_ID_FIELD = 'childId';
    protected const CREATED_AT_FIELD = 'createdAt';
    protected const UPDATED_AT_FIELD = 'updatedAt';

    public function __construct()
    {
        parent::__construct('product_configurator_dependency');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::CONFIGURATOR_SET_ID_FIELD] = (new IntField('configurator_set_id'))->setFlags(new Required());
        $this->fields[self::PARENT_ID_FIELD] = new IntField('parent_id');
        $this->fields[self::CHILD_ID_FIELD] = new IntField('child_id');
        $this->fields[self::CREATED_AT_FIELD] = new DateField('created_at');
        $this->fields[self::UPDATED_AT_FIELD] = new DateField('updated_at');
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductConfiguratorDependencyResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Product\Event\ProductConfiguratorDependencyWrittenEvent
    {
        $event = new \Shopware\Product\Event\ProductConfiguratorDependencyWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductConfiguratorDependencyResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductConfiguratorDependencyResource::createWrittenEvent($updates, $context));
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
