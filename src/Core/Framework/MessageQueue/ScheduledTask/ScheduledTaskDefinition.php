<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\ScheduledTask;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\MessageQueue\DeadMessage\DeadMessageDefinition;

class ScheduledTaskDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'scheduled_task';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_FAILED = 'failed';

    public const STATUS_INACTIVE = 'inactive';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ScheduledTaskCollection::class;
    }

    public function getEntityClass(): string
    {
        return ScheduledTaskEntity::class;
    }

    public function getDefaults(): array
    {
        return ['nextExecutionTime' => new \DateTime()];
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            (new StringField('scheduled_task_class', 'scheduledTaskClass', 512))->setFlags(new Required()),
            (new IntField('run_interval', 'runInterval', 0))->setFlags(new Required()),
            (new StringField('status', 'status'))->setFlags(new Required()),
            new DateTimeField('last_execution_time', 'lastExecutionTime'),
            (new DateTimeField('next_execution_time', 'nextExecutionTime'))->setFlags(new Required()),

            (new OneToManyAssociationField('deadMessages', DeadMessageDefinition::class, 'scheduled_task_id'))->addFlags(new SetNullOnDelete()),
        ]);
    }
}
