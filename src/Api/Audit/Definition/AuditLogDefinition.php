<?php declare(strict_types=1);

namespace Shopware\Api\Audit\Definition;

use Shopware\Api\Audit\Collection\AuditLogBasicCollection;
use Shopware\Api\Audit\Event\AuditLog\AuditLogWrittenEvent;
use Shopware\Api\Audit\Repository\AuditLogRepository;
use Shopware\Api\Audit\Struct\AuditLogBasicStruct;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;

class AuditLogDefinition extends EntityDefinition
{
    /**
     * @var FieldCollection
     */
    protected static $primaryKeys;

    /**
     * @var FieldCollection
     */
    protected static $fields;

    /**
     * @var EntityExtensionInterface[]
     */
    protected static $extensions = [];

    public static function getEntityName(): string
    {
        return 'audit_log';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('action', 'action'))->setFlags(new Required()),
            (new StringField('entity', 'entity'))->setFlags(new Required()),
            (new DateField('created_at', 'createdAt'))->setFlags(new Required()),
            new IdField('user_id', 'userId'),
            new IdField('foreign_key', 'foreignKey'),
            new LongTextField('payload', 'payload'),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return AuditLogRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return AuditLogBasicCollection::class;
    }

    public static function getWrittenEventClass(): string
    {
        return AuditLogWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return AuditLogBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }
}
