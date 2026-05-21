<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\DataAbstractionLayer\Definition;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Collection\UcpSalesChannelConfigCollection;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpSalesChannelConfigEntity;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * @internal
 */
#[Package('framework')]
class UcpSalesChannelConfigDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ucp_sales_channel_config';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return UcpSalesChannelConfigEntity::class;
    }

    public function getCollectionClass(): string
    {
        return UcpSalesChannelConfigCollection::class;
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
            (new BoolField('active', 'active'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new StringField('ucp_version', 'ucpVersion'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new StringField('profile_uri_strategy', 'profileUriStrategy'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new StringField('custom_profile_uri', 'customProfileUri'))->addFlags(new ApiAware(AdminApiSource::class)),
            (new JsonField('enabled_capabilities', 'enabledCapabilities'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new JsonField('enabled_transports', 'enabledTransports'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new StringField('continue_url_template', 'continueUrlTemplate'))->addFlags(new ApiAware(AdminApiSource::class)),
            (new JsonField('platform_allowlist', 'platformAllowlist'))->addFlags(new ApiAware(AdminApiSource::class)),
            (new JsonField('discovery_budget', 'discoveryBudget'))->addFlags(new ApiAware(AdminApiSource::class)),
            new StringField('webhook_url_override', 'webhookUrlOverride'),
            // Signature verification policy: how strictly inbound platform
            // requests are verified per RFC 9421. One of
            //   "off"     — no verification (development only)
            //   "log"     — verify; log failures but accept the request
            //   "strict"  — verify; reject the request on any failure
            (new StringField('signature_policy', 'signaturePolicy'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            // Required Idempotency-Key for non-idempotent UCP operations.
            (new BoolField('idempotency_required', 'idempotencyRequired'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            new CustomFields(),

            (new OneToOneAssociationField('salesChannel', 'sales_channel_id', 'id', SalesChannelDefinition::class, false))
                ->addFlags(new ApiAware(AdminApiSource::class)),
            (new OneToManyAssociationField('signingKeys', UcpSigningKeyDefinition::class, 'sales_channel_id', 'sales_channel_id'))
                ->addFlags(new ApiAware(AdminApiSource::class), new CascadeDelete()),
        ]);
    }

    protected function defaultFields(): array
    {
        return [
            (new CreatedAtField())->addFlags(new ApiAware(AdminApiSource::class)),
            (new UpdatedAtField())->addFlags(new ApiAware(AdminApiSource::class)),
        ];
    }
}
