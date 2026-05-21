<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Persister;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionCollection;
use Shopware\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionEntity;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\Persister\RuleConditionPersister;
use Shopware\Core\Framework\App\Lifecycle\ScriptFileReader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture;
use Shopware\Tests\Unit\Core\Framework\App\Manifest\ManifestFixture;

/**
 * @internal
 */
#[CoversClass(RuleConditionPersister::class)]
class RuleConditionPersisterTest extends TestCase
{
    public function testPersistCreatesRuleConditionsFromManifest(): void
    {
        $app = $this->createAppWithRuleConditions();
        $conditionRepository = $this->createConditionRepository();

        $scriptReader = $this->createMock(ScriptFileReader::class);
        $scriptReader->expects($this->exactly(2))
            ->method('getScriptContent')
            ->with($app, '/rule-conditions/mock.twig')
            ->willReturn('{% return true %}');

        $manifest = ManifestFixture::empty()
            ->withName('withRuleConditions')
            ->withRuleCondition('testcondition0')
            ->withRuleCondition('testcondition1');

        $persister = new RuleConditionPersister(
            $scriptReader,
            $conditionRepository,
            AppFixture::createAppRepository($app),
        );

        $persister->persist(AppFixture::createInstallContext($app, $manifest));

        $payloads = $this->indexPayloadsByIdentifier($conditionRepository->getPayloads(StaticEntityRepository::UPSERT));

        static::assertCount(2, $payloads);

        foreach ($payloads as $identifier => $payload) {
            static::assertStringContainsString('app\withRuleConditions_', $identifier);
            static::assertSame($app->getId(), $payload['appId']);
            static::assertSame('{% return true %}', $payload['script']);
            static::assertTrue($payload['active']);
            static::assertSame([], $payload['config']);
        }
    }

    public function testPersistUpdatesExistingRuleConditionsAndDeletesRemovedOnes(): void
    {
        $existingConditionId = Uuid::randomHex();
        $removedConditionId = Uuid::randomHex();
        $app = $this->createAppWithRuleConditions(
            $this->createCondition($existingConditionId, 'app\withRuleConditions_testcondition0'),
            $this->createCondition($removedConditionId, 'app\withRuleConditions_testcondition1'),
        );

        $conditionRepository = $this->createConditionRepository();

        $scriptReader = $this->createMock(ScriptFileReader::class);
        $scriptReader->expects($this->once())
            ->method('getScriptContent')
            ->with($app, '/rule-conditions/mock.twig')
            ->willReturn('{% return true %}');

        $manifest = ManifestFixture::empty()
            ->withName('withRuleConditions')
            ->withRuleCondition('testcondition0');

        $persister = new RuleConditionPersister(
            $scriptReader,
            $conditionRepository,
            AppFixture::createAppRepository($app),
        );

        $persister->persist(AppFixture::createUpdateContext($app, $manifest));

        $upserts = $conditionRepository->getPayloads(StaticEntityRepository::UPSERT);

        static::assertCount(1, $upserts);
        static::assertSame($existingConditionId, $upserts[0]['id']);
        static::assertSame('app\withRuleConditions_testcondition0', $upserts[0]['identifier']);
        static::assertSame([], $upserts[0]['config']);

        static::assertSame([['id' => $removedConditionId]], $conditionRepository->getPayloads(StaticEntityRepository::DELETE));
    }

    public function testActivateUpdatesInactiveRuleConditions(): void
    {
        $app = $this->createAppWithRuleConditions();
        $conditionIds = [Uuid::randomHex(), Uuid::randomHex()];
        $conditionRepository = $this->createConditionRepository(...$conditionIds);

        $persister = new RuleConditionPersister(
            $this->createMock(ScriptFileReader::class),
            $conditionRepository,
            AppFixture::createAppRepository($app),
        );

        $persister->activate($app, Context::createDefaultContext());

        static::assertSame([
            ['id' => $conditionIds[0], 'active' => true],
            ['id' => $conditionIds[1], 'active' => true],
        ], $conditionRepository->getPayloads(StaticEntityRepository::UPDATE));
    }

    public function testDeactivateUpdatesActiveRuleConditions(): void
    {
        $app = $this->createAppWithRuleConditions();
        $conditionIds = [Uuid::randomHex(), Uuid::randomHex()];
        $conditionRepository = $this->createConditionRepository(...$conditionIds);

        $persister = new RuleConditionPersister(
            $this->createMock(ScriptFileReader::class),
            $conditionRepository,
            AppFixture::createAppRepository($app),
        );

        $persister->deactivate($app, Context::createDefaultContext());

        static::assertSame([
            ['id' => $conditionIds[0], 'active' => false],
            ['id' => $conditionIds[1], 'active' => false],
        ], $conditionRepository->getPayloads(StaticEntityRepository::UPDATE));
    }

    /**
     * @return StaticEntityRepository<AppScriptConditionCollection>
     */
    private function createConditionRepository(string ...$conditionIds): StaticEntityRepository
    {
        /** @var StaticEntityRepository<AppScriptConditionCollection> $conditionRepository */
        $conditionRepository = new StaticEntityRepository([]);
        $conditionRepository->addSearch($conditionIds);

        return $conditionRepository;
    }

    private function createAppWithRuleConditions(AppScriptConditionEntity ...$conditions): AppEntity
    {
        $app = AppFixture::createAppEntity('withRuleConditions');
        $app->setScriptConditions(new AppScriptConditionCollection($conditions));

        return $app;
    }

    private function createCondition(string $id, string $identifier): AppScriptConditionEntity
    {
        $condition = new AppScriptConditionEntity();
        $condition->setId($id);
        $condition->setIdentifier($identifier);

        return $condition;
    }

    /**
     * @param list<array<string, mixed>> $payloads
     *
     * @return array<string, array<string, mixed>>
     */
    private function indexPayloadsByIdentifier(array $payloads): array
    {
        $indexed = [];

        foreach ($payloads as $payload) {
            static::assertIsString($payload['identifier']);

            $indexed[$payload['identifier']] = $payload;
        }

        return $indexed;
    }
}
