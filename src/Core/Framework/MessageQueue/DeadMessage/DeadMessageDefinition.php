<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\DeadMessage;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReadProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;

class DeadMessageDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'dead_message';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return DeadMessageCollection::class;
    }

    public function getEntityClass(): string
    {
        return DeadMessageEntity::class;
    }

    public function getDefaults(): array
    {
        return ['errorCount' => 1, 'encrypted' => false];
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),

            (new LongTextField('original_message_class', 'originalMessageClass'))->setFlags(new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),
            (new BlobField('serialized_original_message', 'serializedOriginalMessage'))->addFlags(new Required(), new ReadProtected(SalesChannelApiSource::class, AdminApiSource::class), new WriteProtected(Context::SYSTEM_SCOPE)),
            (new LongTextField('handler_class', 'handlerClass'))->setFlags(new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),
            (new BoolField('encrypted', 'encrypted'))->setFlags(new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),

            (new IntField('error_count', 'errorCount', 0))->setFlags(new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),

            (new DateTimeField('next_execution_time', 'nextExecutionTime'))->setFlags(new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),

            (new LongTextField('exception', 'exception'))->setFlags(new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),
            (new LongTextField('exception_message', 'exceptionMessage'))->setFlags(new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),
            (new LongTextField('exception_file', 'exceptionFile'))->setFlags(new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),
            (new IntField('exception_line', 'exceptionLine'))->setFlags(new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),

            new FkField('scheduled_task_id', 'scheduledTaskId', ScheduledTaskDefinition::class),

            new ManyToOneAssociationField('scheduledTask', 'scheduled_task_id', ScheduledTaskDefinition::class, 'id', false),
        ]);
    }
}
