<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Acl\Role;

use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReadProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\System\User\UserDefinition;

class AclUserRoleDefinition extends MappingEntityDefinition
{
    public const ENTITY_NAME = 'acl_user_role';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('user_id', 'userId', UserDefinition::class))->addFlags(new PrimaryKey(), new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),
            (new FkField('acl_role_id', 'aclRoleId', AclRoleDefinition::class))->addFlags(new PrimaryKey(), new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),

            new CreatedAtField(),
            new UpdatedAtField(),

            (new ManyToOneAssociationField('user', 'user_id', UserDefinition::class))
                ->addFlags(new ReadProtected(SalesChannelApiSource::class)),

            (new ManyToOneAssociationField('aclRole', 'acl_role_id', AclRoleDefinition::class))
                ->addFlags(new ReadProtected(SalesChannelApiSource::class)),
        ]);
    }
}
