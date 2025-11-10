<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Adapter\Entity\CategoryContentLayout;

use Shopware\Core\Content\ContentSystem\Adapter\Entity\ContentLayoutAssignableDefinitionInterface;
use Shopware\Core\Content\ContentSystem\Helper\ContentLayoutMetadataDeriver;
use Shopware\Core\Content\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopware\Core\Content\ContentSystem\Routing\Field\ParameterBindingsField;
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

#[Package('discovery')]
class CategoryContentLayoutDefinition extends EntityDefinition implements ContentLayoutAssignableDefinitionInterface
{
    final public const ENTITY_NAME = 'category_content_layout';

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

    public function getContentLayoutEntityIdField(): string
    {
        return $this->getMetadataDeriver()->deriveEntityIdField($this->getContentLayoutEntityType());
    }

    public function getContentLayoutEntityType(): string
    {
        return 'category';
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
        // Category pages currently have no page-level data requirements
        // This can be extended to add navigation, footer, or other global requirements
        return [];
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
