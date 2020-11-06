<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App;

use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\AppService;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleIterator;
use Shopware\Core\Framework\App\Lifecycle\AppLoader;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait AppSystemTestBehaviour
{
    /**
     * @after
     * @before
     */
    public function resetActiveApps(): void
    {
        $activeAppLoader = $this->getContainer()->get(ActiveAppsLoader::class, ContainerInterface::NULL_ON_INVALID_REFERENCE);

        if (!$activeAppLoader) {
            return;
        }

        $activeAppLoader->resetActiveApps();
    }

    abstract protected function getContainer(): ContainerInterface;

    protected function loadAppsFromDir(string $appDir, bool $activateApps = true): void
    {
        $appService = new AppService(
            new AppLifecycleIterator(
                $this->getContainer()->get('app.repository'),
                new AppLoader($appDir)
            ),
            $this->getContainer()->get(AppLifecycle::class)
        );

        $appService->refreshApps($activateApps, Context::createDefaultContext());
    }
}
