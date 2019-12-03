<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Acl\Resource;

use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReadProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class AclResourceDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'acl_resource';

    public const PRIVILEGE_LIST = 'list';
    public const PRIVILEGE_DETAIL = 'detail';
    public const PRIVILEGE_CREATE = 'create';
    public const PRIVILEGE_UPDATE = 'update';
    public const PRIVILEGE_ASSIGN = 'assign';
    public const PRIVILEGE_DELETE = 'delete';

    public function getEntityName(): string
    {
        return 'acl_resource';
    }

    public function getCollectionClass(): string
    {
        return AclResourceCollection::class;
    }

    public function getEntityClass(): string
    {
        return AclResourceEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('resource', 'resource'))->addFlags(new PrimaryKey(), new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),
            (new StringField('privilege', 'privilege'))->addFlags(new PrimaryKey(), new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),
            (new FkField('acl_role_id', 'aclRoleId', AclRoleDefinition::class))->addFlags(new PrimaryKey(), new Required()),

            new CreatedAtField(),
            new UpdatedAtField(),

            (new ManyToOneAssociationField('aclRole', 'acl_role_id', AclRoleDefinition::class))
                ->addFlags(new ReadProtected(SalesChannelApiSource::class)),
        ]);
    }
}
