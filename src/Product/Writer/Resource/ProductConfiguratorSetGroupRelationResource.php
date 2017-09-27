<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ProductConfiguratorSetGroupRelationResource extends Resource
{
    protected const SET_ID_FIELD = 'setId';
    protected const UUID_FIELD = 'uuid';
    protected const GROUP_ID_FIELD = 'groupId';
    protected const CREATED_AT_FIELD = 'createdAt';
    protected const UPDATED_AT_FIELD = 'updatedAt';

    public function __construct()
    {
        parent::__construct('product_configurator_set_group_relation');

        $this->primaryKeyFields[self::SET_ID_FIELD] = new IntField('set_id');
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->primaryKeyFields[self::GROUP_ID_FIELD] = new IntField('group_id');
        $this->fields[self::CREATED_AT_FIELD] = new DateField('created_at');
        $this->fields[self::UPDATED_AT_FIELD] = new DateField('updated_at');
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductConfiguratorSetGroupRelationResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Product\Event\ProductConfiguratorSetGroupRelationWrittenEvent
    {
        $event = new \Shopware\Product\Event\ProductConfiguratorSetGroupRelationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductConfiguratorSetGroupRelationResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductConfiguratorSetGroupRelationResource::createWrittenEvent($updates, $context));
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
