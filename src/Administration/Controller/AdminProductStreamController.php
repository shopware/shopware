<?php declare(strict_types=1);

namespace Shopware\Administration\Controller;

use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Package('administration')]
class AdminProductStreamController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ProductDefinition $productDefinition,
        private readonly SalesChannelRepository $salesChannelProductRepository,
        private readonly SalesChannelContextServiceInterface $salesChannelContextService,
        private readonly RequestCriteriaBuilder $criteriaBuilder
    ) {
    }

    #[Route(path: '/api/_admin/product-stream-preview/{salesChannelId}', name: 'api.admin.product-stream-preview', defaults: ['_routeScope' => ['administration']], methods: ['POST'])]
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

        if (empty($request->request->all('ids'))) {
            $request->request->remove('ids');
        }

        $criteria = $this->criteriaBuilder->handleRequest(
            $request,
            new Criteria(),
            $this->productDefinition,
            $context
        );

        $criteria->setTotalCountMode(1);
        $criteria->addAssociation('manufacturer');
        $criteria->addAssociation('options.group');

        $availableFilter = new ProductAvailableFilter($salesChannelId, ProductVisibilityDefinition::VISIBILITY_ALL);
        $queries = $availableFilter->getQueries();
        // remove query for active field as we also want to preview inactive products
        array_pop($queries);
        $availableFilter->assign(['queries' => $queries]);
        $criteria->addFilter($availableFilter);

        $previewResult = $this->salesChannelProductRepository->search($criteria, $salesChannelContext);

        return new JsonResponse($previewResult);
    }
}
