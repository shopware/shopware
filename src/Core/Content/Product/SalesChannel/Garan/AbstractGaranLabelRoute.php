<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Garan;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
abstract class AbstractGaranLabelRoute
{
    abstract public function getDecorated(): AbstractGaranLabelRoute;

    abstract public function load(string $productId, SalesChannelContext $context): GaranLabelRouteResponse;
}
