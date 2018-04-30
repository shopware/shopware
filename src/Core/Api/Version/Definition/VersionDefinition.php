<?php declare(strict_types=1);

namespace Shopware\Api\Version\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\EntityExistence;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\SearchRanking;
use Shopware\Api\Version\Collection\VersionBasicCollection;
use Shopware\Api\Version\Event\Version\VersionDeletedEvent;
use Shopware\Api\Version\Event\Version\VersionWrittenEvent;
use Shopware\Api\Version\Repository\VersionRepository;
use Shopware\Api\Version\Struct\VersionBasicStruct;

class VersionDefinition extends EntityDefinition
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
        return 'version';
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
            (new StringField('name', 'name'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new OneToManyAssociationField('commits', VersionCommitDefinition::class, 'version_id', true),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return VersionRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return VersionBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return VersionDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return VersionWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return VersionBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDefaults(EntityExistence $existence): array
    {
        return [
            'name' => sprintf('Draft (%s)', date('Y-m-d H:i:s')),
            'createdAt' => date(\DateTime::ATOM),
        ];
    }
}
