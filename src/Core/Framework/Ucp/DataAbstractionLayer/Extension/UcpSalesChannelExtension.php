<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\DataAbstractionLayer\Extension;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Definition\UcpSalesChannelConfigDefinition;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Definition\UcpSigningKeyDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * Registers the reverse associations on the SalesChannel definition that
 * pair with `UcpSalesChannelConfigDefinition.salesChannel` (one-to-one) and
 * `UcpSigningKeyDefinition.salesChannel` (many-to-one). Without these the
 * DAL `DefinitionValidator` reports "Missing reverse one-to-* association"
 * for the UCP entities, and `sales_channel.repository` cannot eager-load
 * UCP data through `Criteria::addAssociation('ucpConfig')` or
 * `addAssociation('ucpSigningKeys')`.
 *
 * Both associations are admin-API-only — the storefront has no need to see
 * UCP configuration or signing-key material — and cascade on sales-channel
 * deletion to match the matching `CascadeDelete` flag on the `sales_channel_id`
 * foreign keys in the migrations.
 *
 * @experimental stableVersion:v6.7.11.0 feature:UCP_SERVER
 *
 * @internal
 */
#[Package('framework')]
class UcpSalesChannelExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToOneAssociationField(
                'ucpConfig',
                'id',
                'sales_channel_id',
                UcpSalesChannelConfigDefinition::class,
                false,
            ))->addFlags(new ApiAware(AdminApiSource::class), new CascadeDelete())
        );

        $collection->add(
            (new OneToManyAssociationField(
                'ucpSigningKeys',
                UcpSigningKeyDefinition::class,
                'sales_channel_id',
                'id'
            ))->addFlags(new ApiAware(AdminApiSource::class), new CascadeDelete())
        );
    }

    public function getEntityName(): string
    {
        return SalesChannelDefinition::ENTITY_NAME;
    }
}
