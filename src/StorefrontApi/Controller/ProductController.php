<?php declare(strict_types=1);

namespace Shopware\StorefrontApi\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Product\Definition\ProductDefinition;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Product\Exception\ProductNotFoundException;
use Shopware\Rest\Context\RestContext;
use Shopware\Rest\Response\ResponseFactory;
use Shopware\StorefrontApi\Product\StorefrontProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends Controller
{
    /**
     * @var \Shopware\StorefrontApi\Product\StorefrontProductRepository
     */
    private $repository;

    /**
     * @var ResponseFactory
     */
    private $responseFactory;

    public function __construct(StorefrontProductRepository $repository, ResponseFactory $responseFactory)
    {
        $this->repository = $repository;
        $this->responseFactory = $responseFactory;
    }

    /**
     * @Route("/storefront-api/product", name="storefront.api.product.list")
     *
     * @param Request                                              $request
     * @param \Shopware\StorefrontApi\Context\StorefrontApiContext $context
     *
     * @return Response
     */
    public function listAction(Request $request, StorefrontContext $context): Response
    {
        $criteria = new Criteria();
        if ($request->query->has('offset')) {
            $criteria->setOffset($request->query->getInt('offset'));
        }
        if ($request->query->has('limit')) {
            $criteria->setLimit($request->query->getInt('limit'));
        }

        $result = $this->repository->search($criteria, $context);

        return $this->responseFactory->createListingResponse(
            $result,
            ProductDefinition::class,
            new RestContext($request, $context->getShopContext(), null)
        );
    }

    /**
     * @Route("/storefront-api/product/{productId}", name="storefront.api.product.detail")
     *
     * @param string               $productId
     * @param StorefrontApiContext $context
     *
     * @throws ProductNotFoundException
     *
     * @return Response
     */
    public function detailAction(string $productId, StorefrontContext $context, Request $request): Response
    {
        $products = $this->repository->readDetail([$productId], $context);
        if (!$products->has($productId)) {
            throw new ProductNotFoundException($productId);
        }

        return $this->responseFactory->createDetailResponse(
            $products->get($productId),
            ProductDefinition::class,
            new RestContext($request, $context->getShopContext(), null)
        );
    }
}
