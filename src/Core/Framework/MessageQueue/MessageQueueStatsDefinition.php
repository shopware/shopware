<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\WriteProtection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @deprecated tag:v6.5.0 - use `shopware.increment.message_queue.gateway` service instead
 */
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

    protected function defineProtections(): EntityProtectionCollection
    {
        return new EntityProtectionCollection([new WriteProtection(Context::SYSTEM_SCOPE)]);
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),
            (new StringField('name', 'name'))->addFlags(new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),
            (new IntField('size', 'size', 0))->addFlags(new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),
        ]);
    }
}
