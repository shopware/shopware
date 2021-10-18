<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class IsNewDetector extends AbstractIsNewDetector
{
    private SystemConfigService $systemConfigService;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    public function getDecorated(): AbstractIsNewDetector
    {
        throw new DecorationPatternException(self::class);
    }

    public function isNew(SalesChannelProductEntity $product, SalesChannelContext $context): bool
    {
        $markAsNewDayRange = $this->systemConfigService->get(
            'core.listing.markAsNew',
            $context->getSalesChannel()->getId()
        );

        $now = new \DateTime();

        return $product->getReleaseDate() instanceof \DateTimeInterface
            && $product->getReleaseDate()->diff($now)->days <= $markAsNewDayRange;
    }
}
