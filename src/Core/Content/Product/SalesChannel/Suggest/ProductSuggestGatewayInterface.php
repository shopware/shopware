<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Suggest;

use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @deprecated tag:v6.3.0 use \Shopware\Core\Content\Product\SalesChannel\Suggest\AbstractProductSuggestRoute instead
 */
interface ProductSuggestGatewayInterface
{
    public function suggest(Request $request, SalesChannelContext $salesChannelContext): EntitySearchResult;
}
