<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Extension\ProductListingCriteriaExtension;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\Adapter\Cache\Event\AddCacheTagEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('inventory')]
class ProductListingRoute extends AbstractProductListingRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ProductListingLoader $listingLoader,
        private readonly EntityRepository $categoryRepository,
        private readonly ProductStreamBuilderInterface $productStreamBuilder,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public function getDecorated(): AbstractProductListingRoute
    {
        throw new DecorationPatternException(self::class);
    }

    public static function buildName(string $categoryId): string
    {
        return 'product-listing-' . $categoryId;
    }

    #[Route(path: '/store-api/product-listing/{categoryId}', name: 'store-api.product.listing', methods: ['POST'], defaults: ['_entity' => 'product'])]
    public function load(string $categoryId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductListingRouteResponse
    {
        $this->dispatcher->dispatch(new AddCacheTagEvent(self::buildName($categoryId)));

        $criteria->addFilter(
            new ProductAvailableFilter($context->getSalesChannel()->getId(), ProductVisibilityDefinition::VISIBILITY_ALL)
        );
        $criteria->setTitle('product-listing-route::loading');

        $categoryCriteria = new Criteria([$categoryId]);
        $categoryCriteria->setTitle('product-listing-route::category-loading');
        $categoryCriteria->addFields(['productAssignmentType', 'productStreamId']);
        $categoryCriteria->setLimit(1);

        /** @var PartialEntity|null $category */
        $category = $this->categoryRepository->search($categoryCriteria, $context->getContext())->first();
        if (!$category) {
            throw ProductException::categoryNotFound($categoryId);
        }

        $criteria = $this->extensions->publish(
            name: ProductListingCriteriaExtension::NAME,
            extension: new ProductListingCriteriaExtension($criteria, $context, $categoryId),
            function: function ($criteria, $context, $categoryId) use ($category): Criteria {
                $this->extendCriteria($context, $criteria, $category);

                return $criteria;
            }
        );

        $entities = $this->listingLoader->load($criteria, $context);

        $result = ProductListingResult::createFrom($entities);
        $result->addState(...$entities->getStates());

        $result->setStreamId($category->get('productStreamId'));

        return new ProductListingRouteResponse($result);
    }

    private function extendCriteria(SalesChannelContext $salesChannelContext, Criteria $criteria, PartialEntity $category): void
    {
        $hasProductStream = $category->get('productAssignmentType') === CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM
            && $category->get('productStreamId') !== null;

        if ($hasProductStream) {
            $filters = $this->productStreamBuilder->buildFilters(
                $category->get('productStreamId'),
                $salesChannelContext->getContext()
            );
            $criteria->addFilter(...$filters);

            return;
        }

        $criteria->addFilter(
            new EqualsFilter('product.categoriesRo.id', $category->getId())
        );
    }
}
