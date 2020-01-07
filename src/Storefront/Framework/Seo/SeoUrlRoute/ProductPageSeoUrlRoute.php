<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\SeoUrlRoute;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\SeoTemplateReplacementVariable;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlExtractIdResult;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlMapping;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class ProductPageSeoUrlRoute implements SeoUrlRouteInterface
{
    public const ROUTE_NAME = 'frontend.detail.page';
    public const DEFAULT_TEMPLATE = '{{ product.translated.name }}/{{ product.productNumber }}';

    /**
     * @var ProductDefinition
     */
    private $productDefinition;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    public function __construct(ProductDefinition $productDefinition, EntityRepositoryInterface $productRepository)
    {
        $this->productDefinition = $productDefinition;
        $this->productRepository = $productRepository;
    }

    public function getConfig(): SeoUrlRouteConfig
    {
        return new SeoUrlRouteConfig(
            $this->productDefinition,
            self::ROUTE_NAME,
            self::DEFAULT_TEMPLATE
        );
    }

    public function prepareCriteria(Criteria $criteria): void
    {
        $criteria->addAssociation('manufacturer');
        $criteria->addAssociation('mainCategories.category');
        $criteria->addAssociation('categories');
    }

    public function getMapping(Entity $product, ?SalesChannelEntity $salesChannel): SeoUrlMapping
    {
        if (!$product instanceof ProductEntity) {
            throw new \InvalidArgumentException('Expected ProductEntity');
        }

        $productJson = $product->jsonSerialize();

        $mainCategory = $this->extractMainCategory($product, $salesChannel);
        if ($mainCategory !== null) {
            $productJson['mainCategory'] = $mainCategory->jsonSerialize();
        }

        return new SeoUrlMapping(
            $product,
            ['productId' => $product->getId()],
            [
                'product' => $productJson,
            ]
        );
    }

    public function extractIdsToUpdate(EntityWrittenContainerEvent $event): SeoUrlExtractIdResult
    {
        $ids = [];

        // check if a product was written. If this is the case, its Seo Url must be updated.
        $productEvent = $event->getEventByEntityName(ProductDefinition::ENTITY_NAME);
        if ($productEvent) {
            $ids = $productEvent->getIds();
        }

        // check if any manufacturer (or a manufacturer translation) was modified ...
        $manufacturerIds = [[]];
        $manufacturerEvent = $event->getEventByEntityName(ProductManufacturerDefinition::ENTITY_NAME);
        if ($manufacturerEvent) {
            $manufacturerIds[] = $manufacturerEvent->getIds();
        }

        $manufacturerTranslationEvent = $event->getEventByEntityName(ProductManufacturerTranslationDefinition::ENTITY_NAME);
        if ($manufacturerTranslationEvent) {
            $manufacturerTransPayloads = $manufacturerEvent->getPayloads();

            foreach ($manufacturerTransPayloads as $payload) {
                if (isset($payload['productManufacturerId'])) {
                    $manufacturerIds[] = [$payload['productManufacturerId']];
                }
            }
        }

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        $manufacturerIds = array_unique(array_merge(...$manufacturerIds));

        if (!empty($manufacturerIds)) {
            $categoryIds = $context->disableCache(function (Context $context) use ($manufacturerIds) {
                return $this->productRepository->searchIds(
                    (new Criteria())->addFilter(new EqualsAnyFilter('manufacturerId', $manufacturerIds)),
                    $context
                )->getIds();
            });
            // ... and fetch the affected products
            $ids = array_merge($ids, $categoryIds);
        }

        if (!empty($ids)) {
            $childIds = $context->disableCache(function (Context $context) use ($ids) {
                $criteria = new Criteria();
                $criteria->addFilter(new EqualsAnyFilter('parentId', $ids));

                return $this->productRepository->searchIds($criteria, $context)->getids();
            });

            $ids = array_merge($ids, $childIds);
        }

        return new SeoUrlExtractIdResult($ids);
    }

    public function getSeoVariables(): array
    {
        return ['mainCategory' => new SeoTemplateReplacementVariable(CategoryDefinition::ENTITY_NAME)];
    }

    private function extractMainCategory(ProductEntity $product, ?SalesChannelEntity $salesChannel): ?CategoryEntity
    {
        $mainCategory = null;
        if ($salesChannel !== null) {
            $mainCategoryEntity = $product->getMainCategories()->filterBySalesChannelId($salesChannel->getId())->first();
            $mainCategory = $mainCategoryEntity !== null ? $mainCategoryEntity->getCategory() : null;
        }

        if ($mainCategory === null) {
            $mainCategory = $product->getCategories() ? $product->getCategories()->sortByPosition()->first() : null;
        }

        return $mainCategory;
    }
}
