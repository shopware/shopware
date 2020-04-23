<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Suggest;

use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @deprecated tag:v6.3.0 use \Shopware\Core\Content\Product\SalesChannel\Suggest\AbstractProductSuggestRoute instead
 */
class ProductSuggestGateway implements ProductSuggestGatewayInterface
{
    /**
     * @var AbstractProductSuggestRoute
     */
    private $route;

    public function __construct(
        AbstractProductSuggestRoute $route
    ) {
        $this->route = $route;
    }

    public function suggest(Request $request, SalesChannelContext $context): EntitySearchResult
    {
        return $this->route->load($request, $context)->getListingResult();
    }
}
