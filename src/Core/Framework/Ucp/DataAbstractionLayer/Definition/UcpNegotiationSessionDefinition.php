<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\DataAbstractionLayer\Definition;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Collection\UcpNegotiationSessionCollection;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpNegotiationSessionEntity;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * @internal
 */
#[Package('framework')]
class UcpNegotiationSessionDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ucp_negotiation_session';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return UcpNegotiationSessionEntity::class;
    }

    public function getCollectionClass(): string
    {
        return UcpNegotiationSessionCollection::class;
    }

    public function since(): ?string
    {
        return '6.8.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(AdminApiSource::class), new PrimaryKey(), new Required()),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))
                ->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new LongTextField('platform_profile_uri', 'platformProfileUri'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new StringField('platform_profile_hash', 'platformProfileHash'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new JsonField('active_capabilities', 'activeCapabilities'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new StringField('protocol_version', 'protocolVersion'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new DateTimeField('last_used_at', 'lastUsedAt'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            new DateTimeField('created_at', 'createdAt'),
            new DateTimeField('updated_at', 'updatedAt'),
        ]);
    }
}
