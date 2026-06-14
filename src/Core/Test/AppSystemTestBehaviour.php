<?php declare(strict_types=1);

namespace Shopware\Core\Test;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\After;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\AppService;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleIterator;
use Shopware\Core\Framework\App\Lifecycle\AppLoader;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\System\Snippet\Files\SnippetFileCollection;
use Shopware\Core\System\Snippet\Files\SnippetFileLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait AppSystemTestBehaviour
{
    /**
     * @var list<string>
     */
    private array $appSystemBehaviourAppsInstalledInThisTest = [];

    abstract protected static function getContainer(): ContainerInterface;

    protected function getAppLoader(string $appDir): AppLoader
    {
        return new AppLoader(
            $appDir,
            new NullLogger()
        );
    }

    protected function loadAppsFromDir(string $appDir, bool $activateApps = true): void
    {
        $before = $this->appSystemBehaviourFetchInstalledAppNames();

        $appService = new AppService(
            new AppLifecycleIterator(
                static::getContainer()->get('app.repository'),
                $this->getAppLoader($appDir),
            ),
            static::getContainer()->get(AppLifecycle::class)
        );

        $fails = $appService->doRefreshApps(new AppInstallParameters(activate: $activateApps), Context::createDefaultContext());

        if ($fails !== []) {
            $errors = \array_map(static function (array $fail): string {
                return $fail['exception']->getMessage();
            }, $fails);

            static::fail('App synchronisation failed: ' . \print_r($errors, true));
        }

        $after = $this->appSystemBehaviourFetchInstalledAppNames();
        $this->appSystemBehaviourAppsInstalledInThisTest = \array_values(\array_diff($after, $before));
    }

    protected function reloadAppSnippets(): void
    {
        $collection = static::getContainer()->get(SnippetFileCollection::class);
        $collection->clear();
        static::getContainer()->get(SnippetFileLoader::class)->loadSnippetFilesIntoCollection($collection);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getScriptTraces(): array
    {
        return static::getContainer()
            ->get(ScriptTraces::class)
            ->getTraces();
    }

    #[After]
    protected function deleteShopIdAndResetShopIdProvider(): void
    {
        static::getContainer()->get(ShopIdProvider::class)->deleteShopId();
    }

    /**
     * Apps installed via loadAppsFromDir populate two in-memory caches the
     * surrounding transaction rollback cannot reach: SnippetFileCollection
     * (shared singleton) and ActiveAppsLoader::$activeApps. Reset them so
     * fixture snippets (e.g. swagtheme.en.json overriding document.serviceDateNotice)
     * don't leak into unrelated tests through the Translator catalogue.
     *
     * Done via a local DELETE so the re-scan gets a clean snapshot regardless
     * of whether a transactional behavior's #[After] fires before or after this one.
     */
    #[After]
    protected function cleanUpAppsInstalledInThisTest(): void
    {
        if ($this->appSystemBehaviourAppsInstalledInThisTest === []) {
            return;
        }

        $container = static::getContainer();

        $container->get(Connection::class)->executeStatement(
            'DELETE FROM app WHERE name IN (:names)',
            ['names' => $this->appSystemBehaviourAppsInstalledInThisTest],
            ['names' => ArrayParameterType::STRING]
        );

        $container->get(ActiveAppsLoader::class)->reset();
        $this->reloadAppSnippets();

        $this->appSystemBehaviourAppsInstalledInThisTest = [];
    }

    /**
     * @return list<string>
     */
    private function appSystemBehaviourFetchInstalledAppNames(): array
    {
        /** @var list<string> $names */
        $names = static::getContainer()->get(Connection::class)->fetchFirstColumn('SELECT name FROM app');

        return $names;
    }
}
