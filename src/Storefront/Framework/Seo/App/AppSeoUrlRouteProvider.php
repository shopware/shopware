<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\App;

use Shopware\Core\Framework\App\AppEvents;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('inventory')]
class AppSeoUrlRouteProvider implements EventSubscriberInterface, ResetInterface
{
    /**
     * @var list<AppEntitySeoUrlConfig>|null
     */
    private ?array $routes = null;

    public function __construct(private readonly AppFeatureStorage $storage)
    {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            AppEvents::APP_WRITTEN_EVENT => 'reset',
            AppEvents::APP_DELETED_EVENT => 'reset',
        ];
    }

    /**
     * @return list<AppEntitySeoUrlConfig>
     */
    public function getEntityRoutes(): array
    {
        return $this->routes ??= $this->fetch();
    }

    public function reset(): void
    {
        $this->routes = null;
    }

    /**
     * @return list<AppEntitySeoUrlConfig>
     */
    private function fetch(): array
    {
        $routes = [];

        foreach ($this->storage->forActiveApps(AppEntitySeoUrlConfig::class) as $feature) {
            $routes[] = $feature->config;
        }

        return $routes;
    }
}
