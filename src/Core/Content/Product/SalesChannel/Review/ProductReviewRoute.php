<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Review;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('inventory')]
class ProductReviewRoute extends AbstractProductReviewRoute
{
    /**
     * @internal
     */
    public function __construct(private readonly EntityRepository $repository)
    {
    }

    public function getDecorated(): AbstractProductReviewRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/product/{productId}/reviews', name: 'store-api.product-review.list', methods: ['POST'], defaults: ['_entity' => 'product_review'])]
    public function load(string $productId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductReviewRouteResponse
    {
        $active = new MultiFilter(MultiFilter::CONNECTION_OR, [new EqualsFilter('status', true)]);
        if ($customer = $context->getCustomer()) {
            $active->addQuery(new EqualsFilter('customerId', $customer->getId()));
        }

        $criteria->setTitle('product-review-route');
        $criteria->addFilter(
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                $active,
                new MultiFilter(MultiFilter::CONNECTION_OR, [
                    new EqualsFilter('product.id', $productId),
                    new EqualsFilter('product.parentId', $productId),
                ]),
            ])
        );

        $result = $this->repository->search($criteria, $context->getContext());

        return new ProductReviewRouteResponse($result);
    }
}
