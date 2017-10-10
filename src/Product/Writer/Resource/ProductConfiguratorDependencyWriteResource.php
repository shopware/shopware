<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\Product\Event\ProductConfiguratorDependencyWrittenEvent;

class ProductConfiguratorDependencyWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const CONFIGURATOR_SET_ID_FIELD = 'configuratorSetId';
    protected const PARENT_ID_FIELD = 'parentId';
    protected const CHILD_ID_FIELD = 'childId';

    public function __construct()
    {
        parent::__construct('product_configurator_dependency');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::CONFIGURATOR_SET_ID_FIELD] = (new IntField('configurator_set_id'))->setFlags(new Required());
        $this->fields[self::PARENT_ID_FIELD] = new IntField('parent_id');
        $this->fields[self::CHILD_ID_FIELD] = new IntField('child_id');
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ProductConfiguratorDependencyWrittenEvent
    {
        $event = new ProductConfiguratorDependencyWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
