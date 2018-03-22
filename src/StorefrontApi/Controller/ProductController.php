<?php declare(strict_types=1);

namespace Shopware\StorefrontApi\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\Entity\Search\SearchCriteriaBuilder;
use Shopware\Api\Product\Definition\ProductDefinition;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Product\Exception\ProductNotFoundException;
use Shopware\Rest\Context\RestContext;
use Shopware\Rest\Response\ResponseFactory;
use Shopware\StorefrontApi\Firewall\ContextUser;
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

    /**
     * @var SearchCriteriaBuilder
     */
    private $criteriaBuilder;

    public function __construct(
        StorefrontProductRepository $repository,
        ResponseFactory $responseFactory,
        SearchCriteriaBuilder $criteriaBuilder
    ) {
        $this->repository = $repository;
        $this->responseFactory = $responseFactory;
        $this->criteriaBuilder = $criteriaBuilder;
    }

    /**
     * @Route("/storefront-api/product", name="storefront.api.product.list")
     */
    public function listAction(Request $request): Response
    {
        /** @var ContextUser $user */
        $user = $this->getUser();

        $criteria = $this->criteriaBuilder->handleRequest($request, ProductDefinition::class, $user->getContext()->getShopContext());

        $result = $this->repository->search($criteria, $user->getContext());

        return $this->responseFactory->createListingResponse(
            $result,
            ProductDefinition::class,
            new RestContext($request, $user->getContext()->getShopContext(), null)
        );
    }

    /**
     * @Route("/storefront-api/product/{productId}", name="storefront.api.product.detail")
     * @Method({"GET"})
     */
    public function detailAction(string $productId, Request $request): Response
    {
        /** @var ContextUser $user */
        $user = $this->getUser();

        $products = $this->repository->readDetail([$productId], $user->getContext());
        if (!$products->has($productId)) {
            throw new ProductNotFoundException($productId);
        }

        return $this->responseFactory->createDetailResponse(
            $products->get($productId),
            ProductDefinition::class,
            new RestContext($request, $user->getContext()->getShopContext(), null)
        );
    }
}
