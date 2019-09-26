<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Rule\Repository;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\Rule\CurrencyRule;

class ContextRepositoryTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        $this->repository = $this->getContainer()->get('rule.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->context = Context::createDefaultContext();
    }

    public function testWriteRuleWithObject(): void
    {
        $id = Uuid::randomHex();
        $currencyId = Uuid::randomHex();
        $currencyId2 = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => 'test rule',
            'priority' => 1,
            'conditions' => [
                [
                    'type' => (new AndRule())->getName(),
                    'children' => [
                        [
                            'type' => (new OrRule())->getName(),
                            'children' => [
                                [
                                    'type' => (new CurrencyRule())->getName(),
                                    'value' => [
                                        'currencyIds' => [
                                            $currencyId,
                                            $currencyId2,
                                        ],
                                        'operator' => CurrencyRule::OPERATOR_EQ,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->repository->create([$data], $this->context);

        $rules = $this->repository->search(new Criteria([$id]), $this->context);

        $currencyRule = (new CurrencyRule())->assign(['currencyIds' => [$currencyId, $currencyId2]]);

        static::assertEquals(
            new AndRule([new AndRule([new OrRule([$currencyRule])])]),
            $rules->get($id)->getPayload()
        );
    }
}
