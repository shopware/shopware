<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Storefront;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\Api\Response\ResponseFactory;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\SearchCriteriaBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class StorefrontCategoryController extends Controller
{
    /**
     * @var RepositoryInterface
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
        RepositoryInterface $repository,
        ResponseFactory $responseFactory,
        SearchCriteriaBuilder $criteriaBuilder
    ) {
        $this->repository = $repository;
        $this->responseFactory = $responseFactory;
        $this->criteriaBuilder = $criteriaBuilder;
    }

    /**
     * @Route("/storefront-api/category", name="storefront.api.category.list")
     *
     * @param Request         $request
     * @param CheckoutContext $checkoutContext
     *
     * @return Response
     */
    public function list(Request $request, CheckoutContext $checkoutContext): Response
    {
        $criteria = new Criteria();

        $criteria = $this->criteriaBuilder->handleRequest(
            $request,
            $criteria,
            CategoryDefinition::class,
            $checkoutContext->getContext()
        );

        $result = $this->repository->search($criteria, $checkoutContext->getContext());

        return $this->responseFactory->createListingResponse(
            $result,
            CategoryDefinition::class,
            $request,
            $checkoutContext->getContext()
        );
    }

    /**
     * @Route("/storefront-api/category/{categoryId}", name="storefront.api.category.detail")
     * @Method({"GET"})
     *
     * @param string          $categoryId
     * @param Request         $request
     * @param CheckoutContext $checkoutContext
     *
     * @throws CategoryNotFoundException
     *
     * @return Response
     */
    public function detail(string $categoryId, Request $request, CheckoutContext $checkoutContext): Response
    {
        $categories = $this->repository->read(new ReadCriteria([$categoryId]), $checkoutContext->getContext());
        if (!$categories->has($categoryId)) {
            throw new CategoryNotFoundException($categoryId);
        }

        return $this->responseFactory->createDetailResponse(
            $categories->get($categoryId),
            CategoryDefinition::class,
            $request,
            $checkoutContext->getContext()
        );
    }
}
