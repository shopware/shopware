<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\CacheWarmer\Navigation;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Storefront\Framework\Cache\CacheWarmer\CacheRouteWarmer;
use Shopware\Storefront\Framework\Cache\CacheWarmer\WarmUpMessage;

class NavigationRouteWarmer implements CacheRouteWarmer
{
    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    /**
     * @var CategoryDefinition
     */
    private $definition;

    public function __construct(IteratorFactory $iteratorFactory, CategoryDefinition $definition)
    {
        $this->iteratorFactory = $iteratorFactory;
        $this->definition = $definition;
    }

    public function createMessage(SalesChannelDomainEntity $domain, ?array $offset): ?WarmUpMessage
    {
        $iterator = $this->iteratorFactory->createIterator($this->definition, $offset);
        $query = $iterator->getQuery();
        $query
            ->andWhere('`category`.active = 1')
            ->setMaxResults(10);

        $ids = $iterator->fetch();
        if (empty($ids)) {
            return null;
        }

        $ids = array_map(function ($id) {
            return ['navigationId' => $id];
        }, $ids);

        return new WarmUpMessage('frontend.navigation.page', $ids, $iterator->getOffset());
    }
}
