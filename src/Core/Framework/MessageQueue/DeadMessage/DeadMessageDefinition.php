<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\DeadMessage;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ComputedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class DeadMessageDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'dead_message';
    }

    public static function getCollectionClass(): string
    {
        return DeadMessageCollection::class;
    }

    public static function getEntityClass(): string
    {
        return DeadMessageEntity::class;
    }

    public static function getDefaults(EntityExistence $existence): array
    {
        return [
            'errorCount' => 1,
            'encrypted' => false,
        ];
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),

            (new LongTextField('original_message_class', 'originalMessageClass'))->setFlags(new Required()),
            new ComputedField('serialized_original_message', 'serializedOriginalMessage'),
            (new LongTextField('handler_class', 'handlerClass'))->setFlags(new Required()),
            (new BoolField('encrypted', 'encrypted'))->setFlags(new Required()),

            (new IntField('error_count', 'errorCount', 0))->setFlags(new Required()),

            (new DateField('next_execution_time', 'nextExecutionTime'))->setFlags(new Required()),

            (new LongTextField('exception', 'exception'))->setFlags(new Required()),
            (new LongTextField('exception_message', 'exceptionMessage'))->setFlags(new Required()),
            (new LongTextField('exception_file', 'exceptionFile'))->setFlags(new Required()),
            (new IntField('exception_line', 'exceptionLine'))->setFlags(new Required()),

            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
