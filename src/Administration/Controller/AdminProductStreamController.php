<?php declare(strict_types=1);

namespace Shopware\Administration\Controller;

use Shopware\Administration\Framework\Routing\AdministrationRouteScope;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [AdministrationRouteScope::ID]])]
class AdminProductStreamController extends AbstractController
{
    /**
     * @internal
     *
     * @param SalesChannelRepository<ProductCollection> $salesChannelProductRepository
     */
    public function __construct(
        private readonly ProductDefinition $productDefinition,
        private readonly SalesChannelRepository $salesChannelProductRepository,
        private readonly SalesChannelContextServiceInterface $salesChannelContextService,
        private readonly RequestCriteriaBuilder $criteriaBuilder
    ) {
    }

    #[Route(path: '/api/_admin/product-stream-preview/{salesChannelId}', name: 'api.admin.product-stream-preview', methods: ['POST'])]
    public function productStreamPreview(string $salesChannelId, Request $request, Context $context): JsonResponse
    {
        $salesChannelContext = $this->salesChannelContextService->get(
            new SalesChannelContextServiceParameters(
                $salesChannelId,
                Random::getAlphanumericString(32),
                $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID),
                $context->getCurrencyId()
            )
        );

        if ($request->request->all('ids') === []) {
            $request->request->remove('ids');
        }

        $criteria = $this->criteriaBuilder->handleRequest(
            $request,
            new Criteria(),
            $this->productDefinition,
            $context
        );

        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        $criteria->addAssociation('manufacturer');
        $criteria->addAssociation('options.group');

        $availableFilter = new ProductAvailableFilter($salesChannelId, ProductVisibilityDefinition::VISIBILITY_ALL);
        $queries = $availableFilter->getQueries();
        // remove query for active field as we also want to preview inactive products
        array_pop($queries);
        $availableFilter->assign(['queries' => $queries]);
        $criteria->addFilter($availableFilter);

        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        // Mirror the storefront listing's variant grouping so the preview reflects the product stream's
        // "display as group" setting. It is read from the query string to keep the Criteria request body
        // (and therefore its schema) untouched.
        $displayAsGroup = $request->query->getBoolean('displayAsGroup');
        if ($displayAsGroup) {
            $criteria->addGroupField(new FieldGrouping('displayGroup'));
            $criteria->addFilter(new NotEqualsFilter('displayGroup', null));
        }

        $previewResult = $this->salesChannelProductRepository->search($criteria, $salesChannelContext);

        if (!$displayAsGroup) {
            return new JsonResponse($previewResult);
        }

        return new JsonResponse(new EntitySearchResult(
            $previewResult->getEntity(),
            $previewResult->getTotal(),
            $this->resolvePresentationProducts($previewResult->getEntities(), $criteria, $salesChannelContext),
            $previewResult->getAggregations(),
            $previewResult->getCriteria(),
            $previewResult->getContext()
        ));
    }

    /**
     * The grouping returns an arbitrary variant per display group
     */
    private function resolvePresentationProducts(
        ProductCollection $groupedProducts,
        Criteria $criteria,
        SalesChannelContext $salesChannelContext
    ): ProductCollection {
        $mapping = [];
        foreach ($groupedProducts as $product) {
            $variantListingConfig = $product->getVariantListingConfig();

            $presentationId = $variantListingConfig?->getDisplayParent()
                ? $product->getParentId()
                : $variantListingConfig?->getMainVariantId();

            if ($presentationId !== null && $presentationId !== $product->getId()) {
                $mapping[$product->getId()] = $presentationId;
            }
        }

        if ($mapping === []) {
            return $groupedProducts;
        }

        $presentationProducts = $this->salesChannelProductRepository
            ->search($criteria->cloneForRead(array_values(array_unique($mapping))), $salesChannelContext)
            ->getEntities();

        $products = new ProductCollection();
        foreach ($groupedProducts as $product) {
            $products->add($presentationProducts->get($mapping[$product->getId()] ?? '') ?? $product);
        }

        return $products;
    }
}
