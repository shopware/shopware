<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Adapter\Entity\CategoryContentLayout;

use Shopware\Core\Content\Category\SalesChannel\CategoryRoute;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\ContentLayoutAssignableDefinitionInterface;
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
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class CategoryContentLayoutDefinition extends EntityDefinition implements ContentLayoutAssignableDefinitionInterface
{
    final public const ENTITY_NAME = 'category_content_layout';

    final public const CONTENT_LAYOUT_ENTITY_TYPE = 'category';

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
        return CategoryContentLayoutEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CategoryContentLayoutCollection::class;
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
            new DataRequirement(
                self::CONTENT_LAYOUT_ENTITY_TYPE,
                EntityLoader::SOURCE,
                new EntityLoaderConfig(self::CONTENT_LAYOUT_ENTITY_TYPE, 'categoryId', ['media', 'translations'])
            ),
        ];
    }

    public function getCacheTags(string $entityId): array
    {
        return [CategoryRoute::buildName($entityId)];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),

            (new IdField('category_id', 'categoryId'))->addFlags(new ApiAware(), new Required()),

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
