<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('framework')]
final class TwigContextHelper
{
    /**
     * @param array<string, mixed> $twigContext
     */
    public static function getContext(array $twigContext): ?Context
    {
        $context = $twigContext['context'] ?? null;
        if ($context instanceof Context) {
            return $context;
        }

        return self::getSalesChannelContext($twigContext)?->getContext();
    }

    /**
     * @param array<string, mixed> $twigContext
     */
    public static function getSalesChannelContext(array $twigContext): ?SalesChannelContext
    {
        $context = $twigContext['context'] ?? null;
        if ($context instanceof SalesChannelContext) {
            return $context;
        }

        $salesChannelContext = $twigContext['salesChannelContext'] ?? null;
        if ($salesChannelContext instanceof SalesChannelContext) {
            return $salesChannelContext;
        }

        return null;
    }
}
