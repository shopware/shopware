<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Storefront;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Exception\InvalidUuidException;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\RequestCriteriaBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StorefrontCategoryController extends Controller
{
    /**
     * @var RepositoryInterface
     */
    private $repository;

    /**
     * @var RequestCriteriaBuilder
     */
    private $criteriaBuilder;

    public function __construct(
        RepositoryInterface $repository,
        RequestCriteriaBuilder $criteriaBuilder
    ) {
        $this->repository = $repository;
        $this->criteriaBuilder = $criteriaBuilder;
    }

    /**
     * @Route("/storefront-api/category", name="storefront.api.category.list")
     */
    public function list(Request $request, CheckoutContext $checkoutContext, ResponseFactoryInterface $responseFactory): Response
    {
        $criteria = new Criteria();

        $criteria = $this->criteriaBuilder->handleRequest(
            $request,
            $criteria,
            CategoryDefinition::class,
            $checkoutContext->getContext()
        );

        $result = $this->repository->search($criteria, $checkoutContext->getContext());

        return $responseFactory->createListingResponse(
            $result,
            CategoryDefinition::class,
            $request,
            $checkoutContext->getContext()
        );
    }

    /**
     * @Route("/storefront-api/category/{categoryId}", name="storefront.api.category.detail", methods={"GET"})
     *
     * @throws CategoryNotFoundException
     * @throws InvalidUuidException
     */
    public function detail(string $categoryId, Request $request, CheckoutContext $checkoutContext, ResponseFactoryInterface $responseFactory): Response
    {
        $categories = $this->repository->read(new ReadCriteria([$categoryId]), $checkoutContext->getContext());
        if (!$categories->has($categoryId)) {
            throw new CategoryNotFoundException($categoryId);
        }

        return $responseFactory->createDetailResponse(
            $categories->get($categoryId),
            CategoryDefinition::class,
            $request,
            $checkoutContext->getContext()
        );
    }
}
