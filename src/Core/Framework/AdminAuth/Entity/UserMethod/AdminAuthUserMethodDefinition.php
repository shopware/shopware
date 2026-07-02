<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\Entity\UserMethod;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\User\UserDefinition;

/**
 * @experimental stableVersion:v6.9.0 feature:ADMIN_AUTH
 */
#[Package('framework')]
class AdminAuthUserMethodDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'admin_auth_user_method';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AdminAuthUserMethodEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AdminAuthUserMethodCollection::class;
    }

    public function since(): ?string
    {
        return '6.8.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required(), new ApiAware()),
            (new FkField('user_id', 'userId', UserDefinition::class))->addFlags(new Required(), new ApiAware()),
            (new StringField('type', 'type', 50))->addFlags(new Required(), new ApiAware()),
            (new BoolField('active', 'active'))->addFlags(new ApiAware()),
            (new StringField('label', 'label'))->addFlags(new ApiAware()),
            // The encrypted TOTP secret and the WebAuthn credential are never exposed via the API.
            new StringField('secret', 'secret'),
            new JsonField('credential', 'credential'),
            (new DateTimeField('last_used_at', 'lastUsedAt'))->addFlags(new ApiAware()),

            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id', false),
        ]);
    }
}
