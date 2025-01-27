<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cms\Utils\ProductSlider;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductSliderStruct;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\PropertyNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

#[Package('discovery')]
class MappedHandler extends AbstractProductSliderHandler
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function getSource(): string
    {
        return 'mapped';
    }

    public function collect(CmsSlotEntity $slot, FieldConfigCollection $config, ResolverContext $resolverContext): ?CriteriaCollection
    {
        if (!$resolverContext instanceof EntityResolverContext) {
            return null;
        }

        $products = $config->get('products');
        $criteria = $this->collectByEntity($resolverContext, $products);

        if (!$criteria) {
            return null;
        }

        $collection = new CriteriaCollection();
        $collection->add(
            self::PRODUCT_SLIDER_ENTITY_FALLBACK . '_' . $slot->getUniqueIdentifier(),
            ProductDefinition::class,
            $criteria
        );

        return $collection;
    }

    public function enrich(CmsSlotEntity $slot, ElementDataCollection $result, ResolverContext $resolverContext): void
    {
        if (!$resolverContext instanceof EntityResolverContext) {
            return;
        }

        $config = $slot->getFieldConfig();
        $productConfig = $config->get('products');

        $slider = new ProductSliderStruct();
        $slot->setData($slider);

        $products = $this->resolveEntityValue($resolverContext->getEntity(), $productConfig->getStringValue());

        if ($products === null) {
            $this->enrichFromSearch(
                $slider,
                $result,
                self::PRODUCT_SLIDER_ENTITY_FALLBACK . '_' . $slot->getUniqueIdentifier(),
                $resolverContext->getSalesChannelContext()
            );

            return;
        }

        $slider->setProducts($products);
    }

    protected function hideUnavailableProducts(SalesChannelContext $context): bool
    {
        return (bool) $this->systemConfigService->get(
            'core.listing.hideCloseoutProductsWhenOutOfStock',
            $context->getSalesChannelId()
        );
    }

    private function resolveEntityValue(Entity $entity, string $path)
    {
        $value = $entity;
        $entityPath = explode('.', $path);

        // if property does not exist, try to omit the first key as it may contain the entity name.
        // E.g. `product.description` does not exist, but will be found if the first part is omitted.
        $smartDetect = true;

        foreach ($entityPath as $entityPathPart) {
            if ($value === null) {
                break;
            }

            try {
                $parentValue = $value;
                $value = match (true) {
                    \is_array($value) => $value[$entityPathPart] ?? null,
                    $value instanceof Entity => $value->get($entityPathPart),
                    $value instanceof Struct => $value->getVars()[$entityPathPart] ?? null,
                    default => null,
                };

                // On the last element, try to get the translation if nothing else was found
                if ($value === null && $parentValue instanceof Entity) {
                    $value = $parentValue->getTranslation($entityPathPart);
                }
            } catch (PropertyNotFoundException|\InvalidArgumentException $e) {
                if (!$smartDetect) {
                    throw $e;
                }
            }

            if ($value === null && !$smartDetect) {
                break;
            }

            $smartDetect = false;
        }

        return $value;
    }

    private function collectByEntity(EntityResolverContext $resolverContext, FieldConfig $config): ?Criteria
    {
        $entityProducts = $this->resolveEntityValue($resolverContext->getEntity(), $config->getStringValue());
        if ($entityProducts !== null) {
            return null;
        }

        $criteria = $this->resolveCriteriaForLazyLoadedRelations($resolverContext, $config);
        if (!Feature::isActive('v6.7.0.0')) {
            $criteria?->addAssociations(self::PRODUCT_ASSOCIATIONS);
        }

        return $criteria;
    }

    private function resolveCriteriaForLazyLoadedRelations(
        EntityResolverContext $resolverContext,
        FieldConfig $config
    ): ?Criteria {
        $field = $this->resolveDefinitionField($resolverContext->getDefinition(), $config->getStringValue());
        if ($field === null) {
            return null;
        }

        $key = null;
        $refDef = null;

        // resolve reverse side to fetch data afterwards
        if ($field instanceof ManyToManyAssociationField) {
            $key = $this->getKeyByManyToMany($field);
            $refDef = $field->getToManyReferenceDefinition();
        } elseif ($field instanceof OneToManyAssociationField) {
            $key = $this->getKeyByOneToMany($field);
            $refDef = $field->getReferenceDefinition();
        }

        if (!$key || !$refDef) {
            return null;
        }

        $key = $refDef->getEntityName() . '.' . $key;

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter($key, $resolverContext->getEntity()->getUniqueIdentifier())
        );

        return $criteria;
    }

    private function resolveDefinitionField(EntityDefinition $definition, string $path): ?Field
    {
        $parts = explode('.', $path);
        $fields = $definition->getFields();

        // if property does not exist, try to omit the first key as it may contain the entity name.
        // E.g. `product.description` does not exist, but will be found if the first part is omitted.
        $smartDetect = true;

        foreach ($parts as $part) {
            $value = $fields->get($part);

            if ($value === null) {
                if (!$smartDetect) {
                    break;
                }
                $smartDetect = false;
                continue;
            }

            if ($value instanceof AssociationField) {
                $fields = $value->getReferenceDefinition()->getFields();
            }

            $smartDetect = false;
        }

        return $value ?? null;
    }

    private function getKeyByManyToMany(ManyToManyAssociationField $field): ?string
    {
        $referenceDefinition = $field->getReferenceDefinition();

        $manyToMany = $field->getToManyReferenceDefinition()->getFields()
            ->filterInstance(ManyToManyAssociationField::class)
            ->filter(static fn (ManyToManyAssociationField $field) => $field->getReferenceDefinition() === $referenceDefinition)
            ->first();

        if (!$manyToMany instanceof ManyToManyAssociationField) {
            return null;
        }

        return $manyToMany->getPropertyName() . '.' . $manyToMany->getReferenceField();
    }

    private function getKeyByOneToMany(OneToManyAssociationField $field): ?string
    {
        $referenceDefinition = $field->getReferenceDefinition();

        $manyToOne = $field->getReferenceDefinition()->getFields()
            ->filterInstance(ManyToOneAssociationField::class)
            ->filter(static fn (ManyToOneAssociationField $field) => $field->getReferenceDefinition() === $referenceDefinition)
            ->first();

        if (!$manyToOne instanceof ManyToOneAssociationField) {
            return null;
        }

        return $manyToOne->getPropertyName() . '.' . $manyToOne->getReferenceField();
    }

    private function enrichFromSearch(
        ProductSliderStruct $slider,
        ElementDataCollection $result,
        string $searchKey,
        SalesChannelContext $context
    ): void {
        $products = $result->get($searchKey)?->getEntities();
        if (!$products instanceof ProductCollection) {
            return;
        }

        if ($this->hideUnavailableProducts($context)) {
            $products = $this->filterOutOutOfStockHiddenCloseoutProducts($products);
        }

        $slider->setProducts($products);
    }
}
