<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Storefront;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Response\ResponseFactory;
use Shopware\Core\Framework\Exception\InvalidUuidException;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class StorefrontProductController extends Controller
{
    /**
     * @var StorefrontProductRepository
     */
    private $repository;

    /**
     * @var ResponseFactory
     */
    private $responseFactory;

    /**
     * @var RequestCriteriaBuilder
     */
    private $criteriaBuilder;

    public function __construct(
        StorefrontProductRepository $repository,
        ResponseFactory $responseFactory,
        RequestCriteriaBuilder $criteriaBuilder
    ) {
        $this->repository = $repository;
        $this->responseFactory = $responseFactory;
        $this->criteriaBuilder = $criteriaBuilder;
    }

    /**
     * @Route("/storefront-api/product", name="storefront.api.product.list")
     */
    public function list(Request $request, CheckoutContext $checkoutContext): Response
    {
        $criteria = new Criteria();

        $criteria = $this->criteriaBuilder->handleRequest(
            $request,
            $criteria,
            ProductDefinition::class,
            $checkoutContext->getContext()
        );

        $result = $this->repository->search($criteria, $checkoutContext);

        return $this->responseFactory->createListingResponse(
            $result,
            ProductDefinition::class,
            $request,
            $checkoutContext->getContext()
        );
    }

    /**
     * @Route("/storefront-api/product/{productId}", name="storefront.api.product.detail")
     * @Method({"GET"})
     *
     * @throws ProductNotFoundException
     * @throws InvalidUuidException
     */
    public function detail(string $productId, Request $request, CheckoutContext $checkoutContext): Response
    {
        if (!Uuid::isValid($productId)) {
            throw new InvalidUuidException($productId);
        }

        $products = $this->repository->read([$productId], $checkoutContext);
        if (!$products->has($productId)) {
            throw new ProductNotFoundException($productId);
        }

        return $this->responseFactory->createDetailResponse(
            $products->get($productId),
            ProductDefinition::class,
            $request,
            $checkoutContext->getContext()
        );
    }
}
