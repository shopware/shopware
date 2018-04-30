<?php declare(strict_types=1);

namespace Shopware\Storefront\Api\Seo\Definition;

use Shopware\Api\Application\Definition\ApplicationDefinition;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Storefront\Api\Seo\Collection\SeoUrlBasicCollection;
use Shopware\Storefront\Api\Seo\Collection\SeoUrlDetailCollection;
use Shopware\Storefront\Api\Seo\Event\SeoUrl\SeoUrlDeletedEvent;
use Shopware\Storefront\Api\Seo\Event\SeoUrl\SeoUrlWrittenEvent;
use Shopware\Storefront\Api\Seo\Repository\SeoUrlRepository;
use Shopware\Storefront\Api\Seo\Struct\SeoUrlBasicStruct;
use Shopware\Storefront\Api\Seo\Struct\SeoUrlDetailStruct;

class SeoUrlDefinition extends EntityDefinition
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
        return 'seo_url';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new IdField('version_id', 'versionId'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('application_id', 'applicationId', ApplicationDefinition::class))->setFlags(new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            (new IdField('foreign_key', 'foreignKey'))->setFlags(new Required()),
            (new IdField('foreign_key_version_id', 'foreignKeyVersionId'))->setFlags(new Required()),
            (new StringField('path_info', 'pathInfo'))->setFlags(new Required()),
            (new StringField('seo_path_info', 'seoPathInfo'))->setFlags(new Required()),
            new BoolField('is_canonical', 'isCanonical'),
            new BoolField('is_modified', 'isModified'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('application', 'application_id', ApplicationDefinition::class, false),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return SeoUrlRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return SeoUrlBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return SeoUrlDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return SeoUrlWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return SeoUrlBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return SeoUrlDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return SeoUrlDetailCollection::class;
    }
}
