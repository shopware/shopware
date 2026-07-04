<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\Entity\OauthIdentity;

use Shopware\Core\Framework\AdminAuth\Entity\Provider\AdminAuthProviderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\User\UserDefinition;

/**
 * @experimental stableVersion:v6.9.0 feature:ADMIN_AUTH
 */
#[Package('framework')]
class AdminAuthOauthIdentityDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'admin_auth_oauth_identity';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AdminAuthOauthIdentityEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AdminAuthOauthIdentityCollection::class;
    }

    public function since(): ?string
    {
        return '6.8.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required(), new ApiAware()),
            // No database-level foreign key: YAML-declared providers have deterministic
            // UUIDs without a corresponding `admin_auth_provider` row.
            (new FkField('provider_id', 'providerId', AdminAuthProviderDefinition::class))
                ->addFlags(new Required(), new ApiAware()),
            (new FkField('user_id', 'userId', UserDefinition::class))->addFlags(new Required(), new ApiAware()),
            (new StringField('sub', 'sub'))->addFlags(new Required(), new ApiAware()),
            (new StringField('email', 'email'))->addFlags(new ApiAware()),

            new ManyToOneAssociationField('provider', 'provider_id', AdminAuthProviderDefinition::class, 'id', false),
            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id', false),
        ]);
    }
}
