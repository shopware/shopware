<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Storefront;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Exception\InvalidUuidException;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StorefrontProductController extends Controller
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
     * @Route("/storefront-api/product", name="storefront.api.product.list")
     */
    public function list(Request $request, CheckoutContext $checkoutContext, ResponseFactoryInterface $responseFactory): Response
    {
        $criteria = new Criteria();

        $criteria = $this->criteriaBuilder->handleRequest(
            $request,
            $criteria,
            ProductDefinition::class,
            $checkoutContext->getContext()
        );

        $result = $this->repository->search($criteria, $checkoutContext);

        return $responseFactory->createListingResponse(
            $result,
            ProductDefinition::class,
            $request,
            $checkoutContext->getContext()
        );
    }

    /**
     * @Route("/storefront-api/product/{productId}", name="storefront.api.product.detail", methods={"GET"})
     *
     * @throws ProductNotFoundException
     * @throws InvalidUuidException
     */
    public function detail(string $productId, Request $request, CheckoutContext $checkoutContext, ResponseFactoryInterface $responseFactory): Response
    {
        if (!Uuid::isValid($productId)) {
            throw new InvalidUuidException($productId);
        }

        $products = $this->repository->read([$productId], $checkoutContext);
        if (!$products->has($productId)) {
            throw new ProductNotFoundException($productId);
        }

        return $responseFactory->createDetailResponse(
            $products->get($productId),
            ProductDefinition::class,
            $request,
            $checkoutContext->getContext()
        );
    }
}
