<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Rule\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Rule\DataAbstractionLayer\Indexing\RulePayloadIndexer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\Framework\Rule\CurrencyRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class RulePayloadIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var RepositoryInterface
     */
    private $repository;

    /**
     * @var RulePayloadIndexer
     */
    private $indexer;

    /**
     * @var Connection
     */
    private $connection;

    public function setUp()
    {
        $this->repository = $this->getContainer()->get('rule.repository');
        $this->indexer = $this->getContainer()->get(RulePayloadIndexer::class);
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->context = Context::createDefaultContext();
    }

    public function createRule(): string
    {
        $id = Uuid::uuid4()->getHex();
        $andId = Uuid::uuid4()->getHex();
        $orId = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => 'test rule',
            'priority' => 1,
            'conditions' => [
                [
                    'id' => $andId,
                    'type' => AndRule::class,
                    'children' => [
                        [
                            'id' => $orId,
                            'parentId' => $andId,
                            'type' => OrRule::class,
                            'children' => [
                                [
                                    'id' => Uuid::uuid4()->getHex(),
                                    'parentId' => $orId,
                                    'type' => CurrencyRule::class,
                                    'value' => [
                                        'currencyIds' => [
                                            'SWAG-CURRENCY-ID-1',
                                            'SWAG-CURRENCY-ID-2',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->repository->create([$data], $this->context);

        return $id;
    }

    public function testIndex()
    {
        $ruleId = $this->createRule();
        $this->connection->update('rule', ['payload' => null], ['1' => '1']);
        $rule = $this->repository->read(new ReadCriteria([$ruleId]), $this->context)->get($ruleId);
        static::assertNull($rule->get('payload'));
        $this->indexer->index(new \DateTime());
        $rule = $this->repository->read(new ReadCriteria([$ruleId]), $this->context)->get($ruleId);
        static::assertNotNull($rule->getPayload());
        static::assertInstanceOf(Rule::class, $rule->getPayload());
        static::assertEquals(
            new AndRule([new OrRule([(new CurrencyRule())->assign(['currencyIds' => ['SWAG-CURRENCY-ID-1', 'SWAG-CURRENCY-ID-2']])])]),
            $rule->getPayload()
        );
    }

    public function testRefresh()
    {
        $ruleId = $this->createRule();
        $rule = $this->repository->read(new ReadCriteria([$ruleId]), $this->context)->get($ruleId);
        static::assertNotNull($rule->getPayload());
        static::assertInstanceOf(Rule::class, $rule->getPayload());
        static::assertEquals(
            new AndRule([new OrRule([(new CurrencyRule())->assign(['currencyIds' => ['SWAG-CURRENCY-ID-1', 'SWAG-CURRENCY-ID-2']])])]),
            $rule->getPayload()
        );
    }

    public function testRefreshWithMultipleRules()
    {
        static::markTestIncomplete('Please implement test');
    }

    public function testIndexWithMultipleRules()
    {
        static::markTestIncomplete('Please implement test');
    }

    public function testIndexWithMultipleRootConditions()
    {
        static::markTestIncomplete('Please implement test');
    }
}
