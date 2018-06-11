<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Version\Definition;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\DateField;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\IntField;
use Shopware\Core\Framework\ORM\Field\JsonField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\VersionDataPayloadField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Version\Collection\VersionCommitDataBasicCollection;
use Shopware\Core\Framework\ORM\Version\Struct\VersionCommitDataBasicStruct;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\ORM\Write\Flag\SearchRanking;

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

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('version_commit_id', 'versionCommitId', VersionCommitDefinition::class))->setFlags(new Required()),
            new ManyToOneAssociationField('commit', 'version_commit_id', VersionCommitDefinition::class, false),
            new IdField('user_id', 'userId'),
            new IntField('auto_increment', 'autoIncrement'),
            (new StringField('entity_name', 'entityName'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new JsonField('entity_id', 'entityId'))->setFlags(new Required()),
            (new StringField('action', 'action'))->setFlags(new Required(), new SearchRanking(self::LOW_SEARCH_RAKING)),
            (new VersionDataPayloadField('payload', 'payload'))->setFlags(new Required(), new SearchRanking(self::LOW_SEARCH_RAKING)),
            (new DateField('created_at', 'createdAt'))->setFlags(new Required()),
        ]);
    }

    public static function getBasicCollectionClass(): string
    {
        return VersionCommitDataBasicCollection::class;
    }

    public static function getBasicStructClass(): string
    {
        return VersionCommitDataBasicStruct::class;
    }
}
