<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Fakes;

use Shopware\Core\Content\Product\Cart\ProductGatewayInterface;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class FakeProductGateway implements ProductGatewayInterface
{
    public function get(array $ids, SalesChannelContext $context): ProductCollection
    {
        return new ProductCollection();
    }
}
