<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\CacheWarmer\Navigation;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Storefront\Framework\Cache\CacheWarmer\CacheRouteWarmer;
use Shopware\Storefront\Framework\Cache\CacheWarmer\WarmUpMessage;

/**
 * @deprecated tag:v6.6.0 - Will be removed, use site crawlers for real cache warming
 */
#[Package('core')]
class NavigationRouteWarmer implements CacheRouteWarmer
{
    /**
     * @internal
     */
    public function __construct(
        private readonly IteratorFactory $iteratorFactory,
        private readonly CategoryDefinition $definition
    ) {
    }

    /**
     * @param array{offset: int|null}|null $offset
     */
    public function createMessage(SalesChannelDomainEntity $domain, ?array $offset): ?WarmUpMessage
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0')
        );

        $iterator = $this->iteratorFactory->createIterator($this->definition, $offset);
        $query = $iterator->getQuery();
        $query
            ->andWhere('`category`.active = 1')
            ->setMaxResults(10);

        $ids = $iterator->fetch();
        if (empty($ids)) {
            return null;
        }

        $ids = array_map(fn ($id) => ['navigationId' => $id], $ids);

        return new WarmUpMessage('frontend.navigation.page', $ids, $iterator->getOffset());
    }
}
