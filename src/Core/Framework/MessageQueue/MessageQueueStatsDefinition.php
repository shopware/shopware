<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class MessageQueueStatsDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'message_queue_stats';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return MessageQueueStatsCollection::class;
    }

    public function getEntityClass(): string
    {
        return MessageQueueStatsEntity::class;
    }

    public function getDefaults(): array
    {
        return ['size' => 0];
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),
            (new StringField('name', 'name'))->setFlags(new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),
            (new IntField('size', 'size', 0))->setFlags(new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),
        ]);
    }
}
