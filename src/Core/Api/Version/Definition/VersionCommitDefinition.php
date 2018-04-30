<?php declare(strict_types=1);

namespace Shopware\Api\Version\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\EntityExistence;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\ReadOnly;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\SearchRanking;
use Shopware\Api\Version\Collection\VersionCommitBasicCollection;
use Shopware\Api\Version\Event\VersionCommitData\VersionCommitDataDeletedEvent;
use Shopware\Api\Version\Event\VersionCommitData\VersionCommitDataWrittenEvent;
use Shopware\Api\Version\Repository\VersionCommitRepository;
use Shopware\Api\Version\Struct\VersionCommitBasicStruct;

class VersionCommitDefinition extends EntityDefinition
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
        return 'version_commit';
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
            (new FkField('version_id', 'versionId', VersionDefinition::class))->setFlags(new Required()),
            new IdField('user_id', 'userId'),
            (new IntField('ai', 'ai'))->setFlags(new ReadOnly()),
            new BoolField('is_merge', 'isMerge'),
            (new StringField('message', 'message'))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new DateField('created_at', 'createdAt'))->setFlags(new Required()),
            (new OneToManyAssociationField('data', VersionCommitDataDefinition::class, 'version_commit_id', true))->setFlags(new CascadeDelete()),
            new ManyToOneAssociationField('version', 'version_id', VersionDefinition::class, false),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return VersionCommitRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return VersionCommitBasicCollection::class;
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
        return VersionCommitBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDefaults(EntityExistence $existence): array
    {
        return [
            'name' => 'auto-save',
            'createdAt' => date(\DateTime::ATOM),
        ];
    }
}
