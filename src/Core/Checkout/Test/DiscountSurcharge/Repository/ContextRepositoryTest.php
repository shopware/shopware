<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\DiscountSurcharge\Repository;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Rule\Specification\Container\AndRule;
use Shopware\Core\Content\Rule\Specification\Container\OrRule;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ContextRepositoryTest extends KernelTestCase
{
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
        self::bootKernel();
<<<<<<< Updated upstream:src/Core/Application/Test/Context/Repository/ContextRepositoryTest.php
        $this->repository = self::$container->get(\Shopware\Core\Checkout\Rule\ContextRuleRepository::class);
        $this->connection = self::$container->get(Connection::class);
=======
        $this->container = self::$kernel->getContainer();
        $this->repository = $this->container->get(\Shopware\Core\Content\Rule\ContextRuleRepository::class);
        $this->connection = $this->container->get(Connection::class);
>>>>>>> Stashed changes:src/Core/Checkout/Test/DiscountSurcharge/Repository/ContextRepositoryTest.php
        $this->context = Context::createDefaultContext(Defaults::TENANT_ID);
        $this->connection->beginTransaction();
    }

    public function tearDown(): void
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function testWriteContextRuleWithObject()
    {
        $id = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => 'test rule',
            'priority' => 1,
            'payload' => new AndRule([new OrRule()]),
        ];

        $this->repository->create([$data], $this->context);

        $rules = $this->repository->readBasic([$id], $this->context);

        $this->assertEquals(
            new AndRule([new OrRule()]),
            $rules->get($id)->getPayload()
        );
    }
}
