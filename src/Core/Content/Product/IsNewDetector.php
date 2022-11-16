<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @package inventory
 */
class IsNewDetector extends AbstractIsNewDetector
{
    private SystemConfigService $systemConfigService;

    /**
     * @internal
     */
    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    public function getDecorated(): AbstractIsNewDetector
    {
        throw new DecorationPatternException(self::class);
    }

    public function isNew(Entity $product, SalesChannelContext $context): bool
    {
        $markAsNewDayRange = $this->systemConfigService->get(
            'core.listing.markAsNew',
            $context->getSalesChannel()->getId()
        );

        $now = new \DateTime();

        /** @var \DateTimeInterface|null $releaseDate */
        $releaseDate = $product->get('releaseDate');

        return $releaseDate instanceof \DateTimeInterface
            && $releaseDate->diff($now)->days <= $markAsNewDayRange;
    }
}
