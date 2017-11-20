<?php declare(strict_types=1);

namespace Shopware\Schema\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Write\Flag\PrimaryKey;
use Shopware\Api\Write\Flag\Required;
use Shopware\Schema\Collection\SchemaVersionBasicCollection;
use Shopware\Schema\Event\SchemaVersion\SchemaVersionWrittenEvent;
use Shopware\Schema\Repository\SchemaVersionRepository;
use Shopware\Schema\Struct\SchemaVersionBasicStruct;

class SchemaVersionDefinition extends EntityDefinition
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
        return 'schema_version';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new StringField('version', 'version'))->setFlags(new PrimaryKey(), new Required()),
            (new DateField('start_date', 'startDate'))->setFlags(new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new DateField('complete_date', 'completeDate'),
            new LongTextField('error_msg', 'errorMsg'),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return SchemaVersionRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return SchemaVersionBasicCollection::class;
    }

    public static function getWrittenEventClass(): string
    {
        return SchemaVersionWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return SchemaVersionBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }
}
