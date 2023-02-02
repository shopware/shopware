<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App;

use Shopware\Core\Framework\App\AppService;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleIterator;
use Shopware\Core\Framework\App\Lifecycle\AppLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\System\CustomEntity\Xml\CustomEntityXmlSchemaValidator;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait AppSystemTestBehaviour
{
    abstract protected function getContainer(): ContainerInterface;

    protected function getAppLoader(string $appDir): AppLoader
    {
        return new AppLoader(
            $appDir,
            $this->getContainer()->getParameter('kernel.project_dir'),
            $this->getContainer()->get(ConfigReader::class),
            $this->getContainer()->get(CustomEntityXmlSchemaValidator::class)
        );
    }

    protected function loadAppsFromDir(string $appDir, bool $activateApps = true): void
    {
        $appService = new AppService(
            new AppLifecycleIterator(
                $this->getContainer()->get('app.repository'),
                $this->getAppLoader($appDir),
            ),
            $this->getContainer()->get(AppLifecycle::class)
        );

        $fails = $appService->doRefreshApps($activateApps, Context::createDefaultContext());

        if (!empty($fails)) {
            $errors = \array_map(fn (array $fail) => $fail['exception']->getMessage(), $fails);

            static::fail('App synchronisation failed: ' . \print_r($errors, true));
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function getScriptTraces(): array
    {
        return $this->getContainer()
            ->get(ScriptTraces::class)
            ->getTraces();
    }
}
