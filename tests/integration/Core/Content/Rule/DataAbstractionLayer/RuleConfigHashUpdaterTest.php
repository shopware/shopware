<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Rule\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionCollection;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class RuleConfigHashUpdaterTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<RuleCollection>
     */
    private EntityRepository $ruleRepository;

    /**
     * @var EntityRepository<RuleConditionCollection>
     */
    private EntityRepository $conditionRepository;

    private Connection $connection;

    private Context $context;

    protected function setUp(): void
    {
        $this->ruleRepository = static::getContainer()->get('rule.repository');
        $this->conditionRepository = static::getContainer()->get('rule_condition.repository');
        $this->connection = static::getContainer()->get(Connection::class);
        $this->context = Context::createDefaultContext();
    }

    public function testRulesWithEquivalentConditionsShareTheirHash(): void
    {
        $original = $this->createRule(['amount' => 100, 'operator' => '>=']);
        $copy = $this->createRule(['operator' => '>=', 'amount' => 100]);
        $other = $this->createRule(['amount' => 200, 'operator' => '>=']);

        static::assertNotNull($this->fetchHash($original['id']));
        static::assertSame($this->fetchHash($original['id']), $this->fetchHash($copy['id']));
        static::assertNotSame($this->fetchHash($original['id']), $this->fetchHash($other['id']));
    }

    public function testChangingAConditionUpdatesTheHash(): void
    {
        $original = $this->createRule(['amount' => 100, 'operator' => '>=']);
        $copy = $this->createRule(['amount' => 100, 'operator' => '>=']);

        $this->conditionRepository->update([
            ['id' => $copy['conditionId'], 'value' => ['amount' => 150, 'operator' => '>=']],
        ], $this->context);

        static::assertNotNull($this->fetchHash($copy['id']));
        static::assertNotSame($this->fetchHash($original['id']), $this->fetchHash($copy['id']));
    }

    public function testDeletingAllConditionsRemovesTheHash(): void
    {
        $rule = $this->createRule(['amount' => 100, 'operator' => '>=']);

        $this->conditionRepository->delete([['id' => $rule['conditionId']]], $this->context);

        static::assertNull($this->fetchHash($rule['id']));
    }

    public function testRulesWithoutActualConditionsHaveNoHash(): void
    {
        static::assertNull($this->fetchHash($this->createRuleWithEmptyContainer()['id']));
    }

    /**
     * @param array<string, mixed> $cartAmount
     *
     * @return array{id: string, conditionId: string}
     */
    private function createRule(array $cartAmount): array
    {
        $ids = ['id' => Uuid::randomHex(), 'conditionId' => Uuid::randomHex()];

        $this->ruleRepository->create([[
            'id' => $ids['id'],
            'name' => 'Cart amount',
            'priority' => 1,
            'conditions' => [
                ['id' => $ids['conditionId'], 'type' => 'cartCartAmount', 'value' => $cartAmount],
            ],
        ]], $this->context);

        return $ids;
    }

    /**
     * @return array{id: string}
     */
    private function createRuleWithEmptyContainer(): array
    {
        $id = Uuid::randomHex();

        $this->ruleRepository->create([[
            'id' => $id,
            'name' => 'Empty',
            'priority' => 1,
            'conditions' => [
                ['type' => 'orContainer', 'children' => [['type' => 'andContainer']]],
            ],
        ]], $this->context);

        return ['id' => $id];
    }

    private function fetchHash(string $ruleId): ?string
    {
        $hash = $this->connection->fetchOne(
            'SELECT config_hash FROM `rule` WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($ruleId)]
        );
        static::assertNotFalse($hash, 'The rule does not exist');

        return $hash;
    }
}
