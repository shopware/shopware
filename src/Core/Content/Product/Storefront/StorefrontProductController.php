<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Storefront;

use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StorefrontProductController extends AbstractController
{
    /**
     * @var StorefrontProductRepository
     */
    private $repository;

    /**
     * @var RequestCriteriaBuilder
     */
    private $criteriaBuilder;

    public function __construct(
        StorefrontProductRepository $repository,
        RequestCriteriaBuilder $criteriaBuilder
    ) {
        $this->repository = $repository;
        $this->criteriaBuilder = $criteriaBuilder;
    }

    /**
     * @Route("/storefront-api/v{version}/product", name="storefront-api.product.list")
     */
    public function list(Request $request, SalesChannelContext $salesChannelContext, ResponseFactoryInterface $responseFactory): Response
    {
        $criteria = new Criteria();

        $criteria = $this->criteriaBuilder->handleRequest(
            $request,
            $criteria,
            ProductDefinition::class,
            $salesChannelContext->getContext()
        );

        $result = $this->repository->search($criteria, $salesChannelContext);

        return $responseFactory->createListingResponse(
            $result,
            ProductDefinition::class,
            $request,
            $salesChannelContext->getContext()
        );
    }

    /**
     * @Route("/storefront-api/v{version}/product/{productId}", name="storefront-api.product.detail", methods={"GET"})
     *
     * @throws ProductNotFoundException
     * @throws InvalidUuidException
     */
    public function detail(string $productId, Request $request, SalesChannelContext $salesChannelContext, ResponseFactoryInterface $responseFactory): Response
    {
        if (!Uuid::isValid($productId)) {
            throw new InvalidUuidException($productId);
        }

        $products = $this->repository->read(new Criteria([$productId]), $salesChannelContext);
        if (!$products->has($productId)) {
            throw new ProductNotFoundException($productId);
        }

        return $responseFactory->createDetailResponse(
            $products->get($productId),
            ProductDefinition::class,
            $request,
            $salesChannelContext->getContext()
        );
    }
}
