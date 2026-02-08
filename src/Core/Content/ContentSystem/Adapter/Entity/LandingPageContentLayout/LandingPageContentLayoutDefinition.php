<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Adapter\Entity\LandingPageContentLayout;

use Shopware\Core\Content\ContentSystem\Adapter\Entity\ContentLayoutAssignableDefinitionInterface;
use Shopware\Core\Content\ContentSystem\Adapter\Field\ParameterBindingsField;
use Shopware\Core\Content\ContentSystem\Helper\ContentLayoutMetadataDeriver;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoader;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfig;
use Shopware\Core\Content\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Content\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopware\Core\Content\LandingPage\SalesChannel\LandingPageRoute;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class LandingPageContentLayoutDefinition extends EntityDefinition implements ContentLayoutAssignableDefinitionInterface
{
    final public const ENTITY_NAME = 'landing_page_content_layout';

    final public const CONTENT_LAYOUT_ENTITY_TYPE = 'landing_page';

    /**
     * @internal
     */
    public function __construct(
        private readonly ?ContentLayoutMetadataDeriver $metadataDeriver = null
    ) {
    }

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return LandingPageContentLayoutEntity::class;
    }

    public function getCollectionClass(): string
    {
        return LandingPageContentLayoutCollection::class;
    }

    public function since(): string
    {
        return '6.7.0.0';
    }

    public function getContentLayoutEntityIdField(): string
    {
        return $this->getMetadataDeriver()->deriveEntityIdField($this->getContentLayoutEntityType());
    }

    public function getContentLayoutEntityType(): string
    {
        return self::CONTENT_LAYOUT_ENTITY_TYPE;
    }

    public function getContentLayoutPathPrefix(): string
    {
        return $this->getMetadataDeriver()->derivePathPrefix($this->getContentLayoutEntityType());
    }

    public function getContentLayoutRoutePattern(): string
    {
        return $this->getMetadataDeriver()->deriveRoutePattern($this->getContentLayoutEntityIdField());
    }

    public function getPageDataRequirements(SalesChannelContext $context): array
    {
        return [
            new DataRequirement(self::CONTENT_LAYOUT_ENTITY_TYPE, EntityLoader::SOURCE, new EntityLoaderConfig(self::CONTENT_LAYOUT_ENTITY_TYPE, 'landingPageId', [])),
        ];
    }

    public function getCacheTags(string $entityId): array
    {
        return [LandingPageRoute::buildName($entityId)];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),

            (new IdField('landing_page_id', 'landingPageId'))->addFlags(new ApiAware(), new Required()),

            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new ApiAware()),
            (new FkField('content_layout_id', 'contentLayoutId', ContentLayoutDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new ParameterBindingsField('parameter_bindings', 'parameterBindings'))->addFlags(new ApiAware()),

            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false),
            new ManyToOneAssociationField('contentLayout', 'content_layout_id', ContentLayoutDefinition::class, 'id', false),
        ]);
    }

    private function getMetadataDeriver(): ContentLayoutMetadataDeriver
    {
        return $this->metadataDeriver ?? new ContentLayoutMetadataDeriver();
    }
}
