<?php declare(strict_types=1);

namespace Shopware\Core\Content\Breadcrumb\SalesChannel;

use Shopware\Core\Content\Breadcrumb\Struct\Breadcrumb;
use Shopware\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @experimental stableVersion:v6.7.0 feature:BREADCRUMB_STORE_API
 */
#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('inventory')]
class BreadcrumbRoute extends AbstractBreadcrumbRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CategoryBreadcrumbBuilder $breadcrumbBuilder,
    ) {
    }

    public function getDecorated(): AbstractBreadcrumbRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/breadcrumb/{id}', name: 'store-api.breadcrumb', requirements: ['id' => '[0-9a-f]{32}'], methods: ['GET'])]
    public function load(Request $request, SalesChannelContext $salesChannelContext): BreadcrumbRouteResponse
    {
        $id = $request->get('id', '');
        $type = $request->get('type', 'product');
        if ($type === 'category') {
            $categories = $this->getCategories($id, $salesChannelContext);
        } else {
            $categories = $this->tryToGetCategoriesFromProductOrCategory(
                $id,
                $request->get('referrerCategoryId', ''),
                $salesChannelContext
            );
        }

        $breadcrumb = new BreadcrumbCollection($categories);

        return new BreadcrumbRouteResponse($breadcrumb);
    }

    /**
     * @return array<int, Breadcrumb>
     */
    private function getCategories(string $id, SalesChannelContext $salesChannelContext): array
    {
        $category = $this->breadcrumbBuilder->loadCategory($id, $salesChannelContext->getContext());

        if ($category === null) {
            return [];
        }

        return $this->breadcrumbBuilder->getCategoryBreadcrumbUrls(
            $category,
            $salesChannelContext->getContext(),
            $salesChannelContext->getSalesChannel()
        );
    }

    /**
     * Simple helper function to retry with category type if product is not found
     *
     * @return array<int, Breadcrumb>
     */
    private function tryToGetCategoriesFromProductOrCategory(string $id, string $referrerCategoryId, SalesChannelContext $salesChannelContext): array
    {
        try {
            $categories = $this->breadcrumbBuilder->getProductBreadcrumbUrls($id, $referrerCategoryId, $salesChannelContext);
        } catch (ProductNotFoundException) {
            $categories = $this->getCategories($id, $salesChannelContext);
        }

        return $categories;
    }
}
