<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\Entity\Provider;

use Shopware\Core\Framework\AdminAuth\Entity\OauthIdentity\AdminAuthOauthIdentityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.9.0 feature:ADMIN_AUTH
 */
#[Package('framework')]
class AdminAuthProviderDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'admin_auth_provider';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AdminAuthProviderEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AdminAuthProviderCollection::class;
    }

    public function since(): ?string
    {
        return '6.8.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required(), new ApiAware()),
            (new StringField('name', 'name'))->addFlags(new Required(), new ApiAware()),
            (new StringField('type', 'type', 50))->addFlags(new Required(), new ApiAware()),
            (new BoolField('active', 'active'))->addFlags(new ApiAware()),
            (new BoolField('is_primary', 'isPrimary'))->addFlags(new ApiAware()),
            (new BoolField('is_second_factor', 'isSecondFactor'))->addFlags(new ApiAware()),
            (new IntField('priority', 'priority'))->addFlags(new ApiAware()),
            (new JsonField('config', 'config'))->addFlags(new ApiAware()),

            (new OneToManyAssociationField('identities', AdminAuthOauthIdentityDefinition::class, 'provider_id'))
                ->addFlags(new CascadeDelete(), new ApiAware()),
        ]);
    }
}
