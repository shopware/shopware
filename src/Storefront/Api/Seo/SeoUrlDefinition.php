<?php declare(strict_types=1);

namespace Shopware\Storefront\Api\Seo;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\BoolField;
use Shopware\Core\Framework\ORM\Field\DateField;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\System\Touchpoint\TouchpointDefinition;

class SeoUrlDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'seo_url';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new IdField('version_id', 'versionId'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('touchpoint_id', 'touchpointId', TouchpointDefinition::class))->setFlags(new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            (new IdField('foreign_key', 'foreignKey'))->setFlags(new Required()),
            (new IdField('foreign_key_version_id', 'foreignKeyVersionId'))->setFlags(new Required()),
            (new StringField('path_info', 'pathInfo'))->setFlags(new Required()),
            (new StringField('seo_path_info', 'seoPathInfo'))->setFlags(new Required()),
            new BoolField('is_canonical', 'isCanonical'),
            new BoolField('is_modified', 'isModified'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('touchpoint', 'touchpoint_id', TouchpointDefinition::class, false),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return SeoUrlCollection::class;
    }

    public static function getStructClass(): string
    {
        return SeoUrlStruct::class;
    }
}
