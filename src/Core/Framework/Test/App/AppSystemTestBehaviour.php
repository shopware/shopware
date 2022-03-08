<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App;

use Shopware\Core\Framework\App\AppService;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleIterator;
use Shopware\Core\Framework\App\Lifecycle\AppLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait AppSystemTestBehaviour
{
    abstract protected function getContainer(): ContainerInterface;

    protected function loadAppsFromDir(string $appDir, bool $activateApps = true): void
    {
        $appService = new AppService(
            new AppLifecycleIterator(
                $this->getContainer()->get('app.repository'),
                new AppLoader(
                    $appDir,
                    $this->getContainer()->getParameter('kernel.project_dir'),
                    $this->getContainer()->get(ConfigReader::class)
                )
            ),
            $this->getContainer()->get(AppLifecycle::class)
        );

        $appService->doRefreshApps($activateApps, Context::createDefaultContext());
    }

    protected function getScriptTraces(): array
    {
        return $this->getContainer()
            ->get(ScriptTraces::class)
            ->getTraces();
    }
}
