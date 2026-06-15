<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Persister;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\Persister\ScriptPersister;
use Shopware\Core\Framework\App\Lifecycle\ScriptFileReader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Script\ScriptCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(ScriptPersister::class)]
class ScriptPersisterTest extends TestCase
{
    public function testActivateUpdatesInactiveScripts(): void
    {
        $scriptIds = [Uuid::randomHex(), Uuid::randomHex()];
        $scriptRepository = $this->buildScriptRepository($scriptIds);

        $this->buildPersister($scriptRepository)->activate($this->buildApp(), Context::createDefaultContext());

        static::assertSame([
            ['id' => $scriptIds[0], 'active' => true],
            ['id' => $scriptIds[1], 'active' => true],
        ], $scriptRepository->getPayloads(StaticEntityRepository::UPDATE));
    }

    public function testDeactivateUpdatesActiveScripts(): void
    {
        $scriptIds = [Uuid::randomHex(), Uuid::randomHex()];
        $scriptRepository = $this->buildScriptRepository($scriptIds);

        $this->buildPersister($scriptRepository)->deactivate($this->buildApp(), Context::createDefaultContext());

        static::assertSame([
            ['id' => $scriptIds[0], 'active' => false],
            ['id' => $scriptIds[1], 'active' => false],
        ], $scriptRepository->getPayloads(StaticEntityRepository::UPDATE));
    }

    /**
     * @param list<string> $scriptIds
     *
     * @return StaticEntityRepository<ScriptCollection>
     */
    private function buildScriptRepository(array $scriptIds): StaticEntityRepository
    {
        /** @var StaticEntityRepository<ScriptCollection> $scriptRepository */
        $scriptRepository = new StaticEntityRepository([]);
        $scriptRepository->addSearch($scriptIds);

        return $scriptRepository;
    }

    /**
     * @param StaticEntityRepository<ScriptCollection> $scriptRepository
     */
    private function buildPersister(StaticEntityRepository $scriptRepository): ScriptPersister
    {
        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([]);

        return new ScriptPersister(
            $this->createMock(ScriptFileReader::class),
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
