<?php declare(strict_types=1);

namespace Shopware\Core\System\OAuthClient;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\OAuthClient\Field\RedirectUriListField;

/**
 * A public application registration, not a service identity or a user authorization.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class OAuthClientDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'oauth_client';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return OAuthClientEntity::class;
    }

    public function getCollectionClass(): string
    {
        return OAuthClientCollection::class;
    }

    public function getDefaults(): array
    {
        return ['active' => true];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->addFlags(new ApiAware(), new Required()),
            (new BoolField('active', 'active'))->addFlags(new ApiAware(), new Required()),
            (new RedirectUriListField('redirect_uris', 'redirectUris'))->addFlags(new ApiAware(), new Required()),
        ]);
    }
}
