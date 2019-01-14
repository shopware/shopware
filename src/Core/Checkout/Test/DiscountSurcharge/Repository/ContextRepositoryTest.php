<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\DiscountSurcharge\Repository;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\Framework\Rule\CurrencyRule;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class ContextRepositoryTest extends TestCase
{
    use KernelTestBehaviour,
        DatabaseTransactionBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var RepositoryInterface
     */
    private $repository;

    /**
     * @var Context
     */
    private $context;

    public function setUp()
    {
        $this->repository = $this->getContainer()->get('rule.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->context = Context::createDefaultContext();
    }

    public function testWriteRuleWithObject(): void
    {
        $id = Uuid::uuid4()->getHex();
        $currencyId = Uuid::uuid4()->getHex();
        $currencyId2 = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => 'test rule',
            'priority' => 1,
            'conditions' => [
                [
                    'type' => AndRule::class,
                    'children' => [
                        [
                            'type' => OrRule::class,
                            'children' => [
                                [
                                    'type' => CurrencyRule::class,
                                    'value' => [
                                        'currencyIds' => [
                                            $currencyId,
                                            $currencyId2,
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

        $rules = $this->repository->read(new ReadCriteria([$id]), $this->context);

        $currencyRule = (new CurrencyRule())->assign(['currencyIds' => [$currencyId, $currencyId2]]);

        static::assertEquals(
            new AndRule([new AndRule([new OrRule([$currencyRule])])]),
            $rules->get($id)->getPayload()
        );
    }
}
