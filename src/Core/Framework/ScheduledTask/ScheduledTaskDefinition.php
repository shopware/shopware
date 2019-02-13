<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ScheduledTask;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\MessageQueue\DeadMessage\DeadMessageDefinition;

class ScheduledTaskDefinition extends EntityDefinition
{
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_FAILED = 'failed';
    public const STATUS_INACTIVE = 'inactive';

    public static function getEntityName(): string
    {
        return 'scheduled_task';
    }

    public static function getCollectionClass(): string
    {
        return ScheduledTaskCollection::class;
    }

    public static function getEntityClass(): string
    {
        return ScheduledTaskEntity::class;
    }

    public static function getDefaults(EntityExistence $existence): array
    {
        return [
            'nextExecutionTime' => new \DateTime(),
        ];
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            (new StringField('scheduled_task_class', 'scheduledTaskClass', 512))->setFlags(new Required()),
            (new IntField('run_interval', 'runInterval', 0))->setFlags(new Required()),
            (new StringField('status', 'status'))->setFlags(new Required()),
            new DateField('last_execution_time', 'lastExecutionTime'),
            (new DateField('next_execution_time', 'nextExecutionTime'))->setFlags(new Required()),

            new OneToManyAssociationField('deadMessages', DeadMessageDefinition::class, 'scheduled_task_id', false),
        ]);
    }
}
