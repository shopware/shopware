<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Persister;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionCollection;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\Persister\RuleConditionPersister;
use Shopware\Core\Framework\App\Lifecycle\ScriptFileReader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(RuleConditionPersister::class)]
class RuleConditionPersisterTest extends TestCase
{
    public function testActivateUpdatesInactiveRuleConditions(): void
    {
        $conditionIds = [Uuid::randomHex(), Uuid::randomHex()];
        $conditionRepository = $this->buildConditionRepository($conditionIds);

        $this->buildPersister($conditionRepository)->activate($this->buildApp(), Context::createDefaultContext());

        static::assertSame([
            ['id' => $conditionIds[0], 'active' => true],
            ['id' => $conditionIds[1], 'active' => true],
        ], $conditionRepository->getPayloads(StaticEntityRepository::UPDATE));
    }

    public function testDeactivateUpdatesActiveRuleConditions(): void
    {
        $conditionIds = [Uuid::randomHex(), Uuid::randomHex()];
        $conditionRepository = $this->buildConditionRepository($conditionIds);

        $this->buildPersister($conditionRepository)->deactivate($this->buildApp(), Context::createDefaultContext());

        static::assertSame([
            ['id' => $conditionIds[0], 'active' => false],
            ['id' => $conditionIds[1], 'active' => false],
        ], $conditionRepository->getPayloads(StaticEntityRepository::UPDATE));
    }

    /**
     * @param list<string> $conditionIds
     *
     * @return StaticEntityRepository<AppScriptConditionCollection>
     */
    private function buildConditionRepository(array $conditionIds): StaticEntityRepository
    {
        /** @var StaticEntityRepository<AppScriptConditionCollection> $conditionRepository */
        $conditionRepository = new StaticEntityRepository([]);
        $conditionRepository->addSearch($conditionIds);

        return $conditionRepository;
    }

    /**
     * @param StaticEntityRepository<AppScriptConditionCollection> $conditionRepository
     */
    private function buildPersister(StaticEntityRepository $conditionRepository): RuleConditionPersister
    {
        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([]);

        return new RuleConditionPersister(
            $this->createMock(ScriptFileReader::class),
            $conditionRepository,
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
