<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Version\Definition;

use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\IntField;
use Shopware\Framework\ORM\Field\JsonArrayField;
use Shopware\Framework\ORM\Field\LongTextField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Framework\ORM\Version\Collection\VersionCommitDataBasicCollection;
use Shopware\Framework\ORM\Version\Event\VersionCommitData\VersionCommitDataDeletedEvent;
use Shopware\Framework\ORM\Version\Event\VersionCommitData\VersionCommitDataWrittenEvent;
use Shopware\Framework\ORM\Version\Repository\VersionCommitDataRepository;
use Shopware\Framework\ORM\Version\Struct\VersionCommitDataBasicStruct;

class VersionCommitDataDefinition extends EntityDefinition
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
        return 'version_commit_data';
    }

    public static function isVersionAware(): bool
    {
        return false;
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('version_commit_id', 'versionCommitId', VersionCommitDefinition::class))->setFlags(new Required()),
            new ManyToOneAssociationField('commit', 'version_commit_id', VersionCommitDefinition::class, false),
            new IdField('user_id', 'userId'),
            new IntField('ai', 'ai'),
            (new StringField('entity_name', 'entityName'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new JsonArrayField('entity_id', 'entityId'))->setFlags(new Required()),
            (new StringField('action', 'action'))->setFlags(new Required(), new SearchRanking(self::LOW_SEARCH_RAKING)),
            (new LongTextField('payload', 'payload'))->setFlags(new Required(), new SearchRanking(self::LOW_SEARCH_RAKING)),
            (new DateField('created_at', 'createdAt'))->setFlags(new Required()),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return VersionCommitDataRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return VersionCommitDataBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return VersionCommitDataDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return VersionCommitDataWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return VersionCommitDataBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }
}
