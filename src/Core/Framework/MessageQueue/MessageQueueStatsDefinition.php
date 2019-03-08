<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\SourceContext;

class MessageQueueStatsDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'message_queue_stats';
    }

    public static function getCollectionClass(): string
    {
        return MessageQueueStatsCollection::class;
    }

    public static function getEntityClass(): string
    {
        return MessageQueueStatsEntity::class;
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
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required(), new WriteProtected(SourceContext::ORIGIN_SYSTEM)),
            (new StringField('name', 'name'))->setFlags(new Required(), new WriteProtected(SourceContext::ORIGIN_SYSTEM)),
            (new IntField('size', 'size', 0))->setFlags(new Required(), new WriteProtected(SourceContext::ORIGIN_SYSTEM)),
        ]);
    }
}
