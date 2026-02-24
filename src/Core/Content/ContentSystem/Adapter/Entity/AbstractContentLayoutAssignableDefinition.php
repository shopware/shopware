<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Adapter\Entity;

use Shopware\Core\Content\ContentSystem\Adapter\Field\ParameterBindingsField;
use Shopware\Core\Content\ContentSystem\Helper\ContentLayoutMetadataDeriver;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoader;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfig;
use Shopware\Core\Content\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Content\ContentSystem\Layout\Entity\ContentLayoutDefinition;
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
 * Shared field definitions and metadata derivation for content layout assignments.
 */
#[Package('discovery')]
abstract class AbstractContentLayoutAssignableDefinition extends EntityDefinition
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ContentLayoutMetadataDeriver $metadataDeriver = new ContentLayoutMetadataDeriver(),
    ) {
    }

    public function since(): string
    {
        return '6.7.0.0';
    }

    /**
     * Returns the field name used to identify the assigned entity in the assignment table.
     *
     * @return non-empty-string
     */
    public function getContentLayoutEntityIdField(): string
    {
        return $this->metadataDeriver->deriveEntityIdField($this->getContentLayoutEntityType());
    }

    /**
     * Returns the database column name for the entity ID field.
     *
     * @return non-empty-string
     */
    public function getContentLayoutEntityIdColumn(): string
    {
        return $this->metadataDeriver->deriveEntityIdColumn($this->getContentLayoutEntityType());
    }

    /**
     * Returns the entity type name used as primary identifier for
     * field derivation, routing, data requirements, and resolution.
     *
     * @return non-empty-string
     */
    abstract public function getContentLayoutEntityType(): string;

    /**
     * Returns the URL path prefix for this entity type.
     *
     * Used by Chain of Responsibility pattern to route requests.
     */
    public function getContentLayoutPathPrefix(): string
    {
        return $this->metadataDeriver->derivePathPrefix($this->getContentLayoutEntityType());
    }

    /**
     * Returns the route pattern with placeholder for entity ID extraction.
     *
     * Used with Symfony's UrlMatcher to extract entity ID from path.
     */
    public function getContentLayoutRoutePattern(): string
    {
        return $this->metadataDeriver->deriveRoutePattern($this->getContentLayoutEntityIdField());
    }

    /**
     * Returns page-level data requirements for this entity type.
     *
     * These requirements are loaded once per page and distributed to all
     * root elements via virtual root pattern during hydration.
     *
     * @return array<DataRequirement>
     */
    public function getPageDataRequirements(SalesChannelContext $context): array
    {
        return [
            new DataRequirement(
                $this->getContentLayoutEntityType(),
                EntityLoader::SOURCE,
                new EntityLoaderConfig($this->getContentLayoutEntityType(), $this->getContentLayoutEntityIdField(), $this->getEntityAssociations())
            ),
        ];
    }

    /**
     * Added to cache context at start of rendering for invalidation
     * when the context entity changes.
     *
     * @return list<string>
     */
    abstract public function getCacheTags(string $entityId): array;

    /**
     * Returns entity associations to eager-load with the page entity.
     *
     * @return list<non-empty-string>
     */
    protected function getEntityAssociations(): array
    {
        return [];
    }

    /**
     * Returns the entity-specific ID field (e.g., product_id, category_id).
     */
    abstract protected function defineEntityIdField(): IdField;

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),

            $this->defineEntityIdField()->addFlags(new ApiAware(), new Required()),

            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new ApiAware()),
            (new FkField('content_layout_id', 'contentLayoutId', ContentLayoutDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new ParameterBindingsField('parameter_bindings', 'parameterBindings'))->addFlags(new ApiAware()),

            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false),
            new ManyToOneAssociationField('contentLayout', 'content_layout_id', ContentLayoutDefinition::class, 'id', false),
        ]);
    }
}
