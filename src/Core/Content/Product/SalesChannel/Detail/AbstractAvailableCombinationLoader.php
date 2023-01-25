<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Detail;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
abstract class AbstractAvailableCombinationLoader
{
    abstract public function getDecorated(): AbstractAvailableCombinationLoader;

    abstract public function load(string $productId, Context $context, string $salesChannelId): AvailableCombinationResult;
}
