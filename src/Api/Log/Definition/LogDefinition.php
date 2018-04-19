<?php declare(strict_types=1);

namespace Shopware\Api\Log\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Log\Collection\LogBasicCollection;
use Shopware\Api\Log\Event\Log\LogDeletedEvent;
use Shopware\Api\Log\Event\Log\LogWrittenEvent;
use Shopware\Api\Log\Repository\LogRepository;
use Shopware\Api\Log\Struct\LogBasicStruct;

class LogDefinition extends EntityDefinition
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
        return 'log';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            (new StringField('type', 'type'))->setFlags(new Required()),
            (new StringField('key', 'key'))->setFlags(new Required()),
            (new LongTextField('text', 'text'))->setFlags(new Required()),
            (new DateField('date', 'date'))->setFlags(new Required()),
            (new StringField('user', 'user'))->setFlags(new Required()),
            (new StringField('ip_address', 'ipAddress'))->setFlags(new Required()),
            (new StringField('user_agent', 'userAgent'))->setFlags(new Required()),
            (new StringField('value4', 'value4'))->setFlags(new Required()),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return LogRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return LogBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return LogDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return LogWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return LogBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }
}
