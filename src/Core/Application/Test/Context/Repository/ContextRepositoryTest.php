<?php declare(strict_types=1);

namespace Shopware\Application\Test\Context\Repository;

use Doctrine\DBAL\Connection;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Checkout\Rule\Specification\Container\AndRule;
use Shopware\Checkout\Rule\Specification\Container\OrRule;
use Shopware\Defaults;
use Shopware\Framework\ORM\RepositoryInterface;
use Shopware\Framework\Struct\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ContextRepositoryTest extends KernelTestCase
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var RepositoryInterface
     */
    private $repository;

    /**
     * @var ApplicationContext
     */
    private $context;

    public function setUp()
    {
        self::bootKernel();
        $this->container = self::$kernel->getContainer();
        $this->repository = $this->container->get(\Shopware\Checkout\Rule\ContextRuleRepository::class);
        $this->connection = $this->container->get(Connection::class);
        $this->context = ApplicationContext::createDefaultContext(Defaults::TENANT_ID);
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
