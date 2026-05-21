<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\DataAbstractionLayer\Definition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Collection\UcpPlatformProfileCacheCollection;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpPlatformProfileCacheEntity;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * @internal
 */
#[Package('framework')]
class UcpPlatformProfileCacheDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ucp_platform_profile_cache';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return UcpPlatformProfileCacheEntity::class;
    }

    public function getCollectionClass(): string
    {
        return UcpPlatformProfileCacheCollection::class;
    }

    public function since(): ?string
    {
        return '6.8.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new LongTextField('profile_uri', 'profileUri'))->addFlags(new ApiAware(), new Required()),
            (new StringField('profile_uri_hash', 'profileUriHash'))->addFlags(new ApiAware(), new Required()),
            (new JsonField('profile_json', 'profileJson'))->addFlags(new ApiAware(), new Required()),
            (new StringField('etag', 'etag'))->addFlags(new ApiAware()),
            (new DateTimeField('fetched_at', 'fetchedAt'))->addFlags(new ApiAware(), new Required()),
            (new DateTimeField('expires_at', 'expiresAt'))->addFlags(new ApiAware(), new Required()),
            (new StringField('verification_status', 'verificationStatus'))->addFlags(new ApiAware(), new Required()),
            (new IntField('failure_count', 'failureCount'))->addFlags(new ApiAware(), new Required()),
            new DateTimeField('created_at', 'createdAt'),
            new DateTimeField('updated_at', 'updatedAt'),
        ]);
    }
}
