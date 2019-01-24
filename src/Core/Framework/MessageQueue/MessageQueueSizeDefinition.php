<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class MessageQueueSizeDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'message_queue_size';
    }

    public static function getCollectionClass(): string
    {
        return MessageQueueSizeCollection::class;
    }

    public static function getEntityClass(): string
    {
        return MessageQueueSizeEntity::class;
    }

    public static function getDefaults(EntityExistence $existence): array
    {
        return [
            'size' => 0,
        ];
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            (new IntField('size', 'size', 0))->setFlags(new Required()),
        ]);
    }
}
