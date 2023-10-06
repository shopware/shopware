<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Detail;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
abstract class AbstractAvailableCombinationLoader
{
    abstract public function getDecorated(): AbstractAvailableCombinationLoader;

    /**
     * @deprecated tag:v6.6.0 - Method will be removed. Use `loadCombinations` instead.
     */
    abstract public function load(string $productId, Context $context, string $salesChannelId): AvailableCombinationResult;

    /**
     * @deprecated tag:v6.6.0 - Method will be marked as abstract and must be implemented.
     *
     * Starting from 6.6 this will be abstract.The current implementation is for backwards compatibility and proxies to the `load` method.
     */
    public function loadCombinations(string $productId, SalesChannelContext $salesChannelContext): AvailableCombinationResult
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            sprintf('Method "%s::%s()" will be marked as abstract in %s. It must be implemented.', self::class, __METHOD__, 'v6.6.0.0')
        );

        return $this->load($productId, $salesChannelContext->getContext(), $salesChannelContext->getSalesChannel()->getId());
    }
}
