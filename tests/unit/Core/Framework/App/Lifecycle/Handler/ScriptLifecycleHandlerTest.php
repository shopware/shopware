<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Shopware\Core\Framework\App\Lifecycle\Handler\ScriptLifecycleHandler;
use Shopware\Core\Framework\App\Lifecycle\ScriptFileReader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\ScriptCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ScriptLifecycleHandler::class)]
class ScriptLifecycleHandlerTest extends TestCase
{
    public function testActivateUpdatesInactiveScripts(): void
    {
        $scriptIds = [Uuid::randomHex(), Uuid::randomHex()];
        $scriptRepository = $this->buildScriptRepository($scriptIds);

        $this->buildPersister($scriptRepository)->activate(new AppActivationContext($this->buildApp(), Context::createDefaultContext()));

        static::assertSame([
            ['id' => $scriptIds[0], 'active' => true],
            ['id' => $scriptIds[1], 'active' => true],
        ], $scriptRepository->getPayloads(StaticEntityRepository::UPDATE));
    }

    public function testDeactivateUpdatesActiveScripts(): void
    {
        $scriptIds = [Uuid::randomHex(), Uuid::randomHex()];
        $scriptRepository = $this->buildScriptRepository($scriptIds);

        $this->buildPersister($scriptRepository)->deactivate(new AppActivationContext($this->buildApp(), Context::createDefaultContext()));

        static::assertSame([
            ['id' => $scriptIds[0], 'active' => false],
            ['id' => $scriptIds[1], 'active' => false],
        ], $scriptRepository->getPayloads(StaticEntityRepository::UPDATE));
    }

    public function testRefreshLoadsTheScriptsOfEveryAppWithOneSearch(): void
    {
        $firstApp = $this->buildApp();
        $firstApp->setScripts(new ScriptCollection());
        $secondApp = $this->buildApp();
        $secondApp->setScripts(new ScriptCollection());

        $appRepository = new StaticEntityRepository([]);
        // the ids of the active apps, then the scripts of all of them in one search
        $appRepository->addSearch([$firstApp->getId(), $secondApp->getId()]);
        $appRepository->addSearch(new AppCollection([$firstApp, $secondApp]));

        $scriptReader = static::createStub(ScriptFileReader::class);
        $scriptReader->method('getScriptPathsForApp')->willReturn([]);

        $handler = new ScriptLifecycleHandler($scriptReader, new StaticEntityRepository([]), $appRepository);
        $handler->refresh();

        // every queued search was consumed: the id lookup and one search for the scripts of both apps
        static::assertSame([], $appRepository->searches);
    }

    /**
     * @param list<string> $scriptIds
     *
     * @return StaticEntityRepository<ScriptCollection>
     */
    private function buildScriptRepository(array $scriptIds): StaticEntityRepository
    {
        $scriptRepository = new StaticEntityRepository([]);
        $scriptRepository->addSearch($scriptIds);

        return $scriptRepository;
    }

    /**
     * @param StaticEntityRepository<ScriptCollection> $scriptRepository
     */
    private function buildPersister(StaticEntityRepository $scriptRepository): ScriptLifecycleHandler
    {
        $appRepository = new StaticEntityRepository([]);

        return new ScriptLifecycleHandler(
            static::createStub(ScriptFileReader::class),
            $scriptRepository,
            $appRepository,
        );
    }

    private function buildApp(): AppEntity
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());

        return $app;
    }
}
