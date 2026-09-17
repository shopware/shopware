<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\App;

use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\App\Aggregate\AppSeoUrlRoute\AppSeoUrlRouteEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('inventory')]
class AppSeoUrlRouteProvider implements EventSubscriberInterface, ResetInterface
{
    final public const CACHE_KEY = 'app-seo-url-routes';

    /**
     * @var EntityCollection<AppSeoUrlRouteEntity>|null
     */
    private ?EntityCollection $routes = null;

    /**
     * @param EntityRepository<EntityCollection<AppSeoUrlRouteEntity>> $repository
     */
    public function __construct(
        private readonly EntityRepository $repository,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'app.written' => 'invalidate',
            'app.deleted' => 'invalidate',
            AppSeoUrlRouteEntity::ENTITY_NAME . '.written' => 'invalidate',
            AppSeoUrlRouteEntity::ENTITY_NAME . '.deleted' => 'invalidate',
        ];
    }

    /**
     * @return EntityCollection<AppSeoUrlRouteEntity>
     */
    public function getEntityRoutes(?string $appId = null): EntityCollection
    {
        return $this->load()->filter(
            static fn (AppSeoUrlRouteEntity $route): bool => $route->entityName !== null
                && $route->defaultTemplate !== null
                && ($appId === null || $route->appId === $appId)
        );
    }

    /**
     * @return EntityCollection<AppSeoUrlRouteEntity>
     */
    public function getStaticRoutes(?string $appId = null): EntityCollection
    {
        return $this->load()->filter(
            static fn (AppSeoUrlRouteEntity $route): bool => $route->entityName === null
                && $route->paths !== null
                && $route->paths !== []
                && ($appId === null || $route->appId === $appId)
        );
    }

    public function invalidate(): void
    {
        $this->reset();
        $this->cache->delete(self::CACHE_KEY);
    }

    public function reset(): void
    {
        $this->routes = null;
    }

    /**
     * @return EntityCollection<AppSeoUrlRouteEntity>
     */
    private function load(): EntityCollection
    {
        if ($this->routes !== null) {
            return $this->routes;
        }

        $fresh = null;

        $value = $this->cache->get(self::CACHE_KEY, function (ItemInterface $item) use (&$fresh) {
            $item->tag([self::CACHE_KEY]);

            $fresh = $this->fetch();

            return CacheValueCompressor::compress($fresh);
        });

        if ($fresh !== null) {
            return $this->routes = $fresh;
        }

        return $this->routes = CacheValueCompressor::uncompress($value);
    }

    /**
     * @return EntityCollection<AppSeoUrlRouteEntity>
     */
    private function fetch(): EntityCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('app-seo-url-routes::load');
        $criteria->addFilter(new EqualsFilter('app.active', true));

        return $this->repository->search($criteria, Context::createDefaultContext())->getEntities();
    }
}
