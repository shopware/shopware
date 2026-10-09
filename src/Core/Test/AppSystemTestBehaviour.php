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
use Shopware\Core\Framework\App\Source\SourceResolver;
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

        // track before failing: a partially failed sync still installs apps whose
        // in-memory state must be cleaned up, or it leaks into subsequent tests
        $after = $this->appSystemBehaviourFetchInstalledAppNames();
        $this->appSystemBehaviourAppsInstalledInThisTest = \array_values(\array_diff($after, $before));

        if ($fails !== []) {
            $errors = \array_map(static function (array $fail): string {
                return \sprintf('%s: %s', $fail['manifest']->getMetadata()->getName(), $fail['exception']->getMessage());
            }, $fails);

            static::fail('App synchronisation failed: ' . \print_r($errors, true));
        }
    }

    protected function reloadAppSnippets(): void
    {
        $collection = static::getContainer()->get(SnippetFileCollection::class);
        $collection->clear();
        static::getContainer()->get(SnippetFileLoader::class)->loadSnippetFilesIntoCollection($collection);
    }

    /**
     * Pass the container of the browser that performed the request (e.g.
     * `$browser->getContainer()`) — script traces are collected by the
     * container that handled the request, which is not the static test
     * container once kernel services are reset between requests.
     *
     * @return array<string, mixed>
     */
    protected function getScriptTraces(?ContainerInterface $container = null): array
    {
        return ($container ?? static::getContainer())
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
        // app filesystems are cached by app name; fixtures across test dirs reuse
        // names (e.g. SwagApp), so a stale entry redirects file lookups of the next
        // test's app to the wrong root
        $container->get(SourceResolver::class)->reset();
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
