<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AgenticDiscovery\DataAbstractionLayer\Extension;

use Shopware\Core\Framework\AgenticDiscovery\DataAbstractionLayer\Definition\AgenticDiscoverySalesChannelConfigDefinition;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * Registers the reverse one-to-one association on the SalesChannel definition
 * that pairs with `AgenticDiscoverySalesChannelConfigDefinition.salesChannel`.
 * Without this the DAL `DefinitionValidator` reports "Missing reverse one-to-*
 * association", and admin clients cannot eager-load the config via
 * `Criteria::addAssociation('agenticDiscoveryConfig')`.
 *
 * The association is admin-API-only — the storefront has no reason to expose
 * discovery configuration through its responses — and cascades on
 * sales-channel deletion to match the `CascadeDelete` semantics on the
 * `sales_channel_id` foreign key in the migration.
 *
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_DISCOVERY
 *
 * @internal
 */
#[Package('framework')]
class AgenticDiscoverySalesChannelExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToOneAssociationField(
                'agenticDiscoveryConfig',
                'id',
                'sales_channel_id',
                AgenticDiscoverySalesChannelConfigDefinition::class,
                false,
            ))->addFlags(new ApiAware(AdminApiSource::class), new CascadeDelete())
        );
    }

    public function getEntityName(): string
    {
        return SalesChannelDefinition::ENTITY_NAME;
    }
}
