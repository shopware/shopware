<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AgenticDiscovery\DataAbstractionLayer\Definition;

use Shopware\Core\Framework\AgenticDiscovery\DataAbstractionLayer\Collection\AgenticDiscoverySalesChannelConfigCollection;
use Shopware\Core\Framework\AgenticDiscovery\DataAbstractionLayer\Entity\AgenticDiscoverySalesChannelConfigEntity;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_DISCOVERY
 *
 * @internal
 */
#[Package('framework')]
class AgenticDiscoverySalesChannelConfigDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'agentic_discovery_sales_channel_config';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AgenticDiscoverySalesChannelConfigEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AgenticDiscoverySalesChannelConfigCollection::class;
    }

    public function since(): ?string
    {
        return '6.7.11.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(AdminApiSource::class), new PrimaryKey(), new Required()),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))
                ->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new BoolField('active', 'active'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new BoolField('expose_agents_md', 'exposeAgentsMd'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new BoolField('expose_llms_txt', 'exposeLlmsTxt'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new BoolField('expose_llms_full_txt', 'exposeLlmsFullTxt'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new BoolField('expose_agentic_sitemap', 'exposeAgenticSitemap'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new LongTextField('custom_intro', 'customIntro'))->addFlags(new ApiAware(AdminApiSource::class)),
            (new JsonField('custom_agent_rules', 'customAgentRules'))->addFlags(new ApiAware(AdminApiSource::class)),
            (new JsonField('custom_sections', 'customSections'))->addFlags(new ApiAware(AdminApiSource::class)),
            new CustomFields(),

            (new OneToOneAssociationField('salesChannel', 'sales_channel_id', 'id', SalesChannelDefinition::class, false))
                ->addFlags(new ApiAware(AdminApiSource::class)),
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
