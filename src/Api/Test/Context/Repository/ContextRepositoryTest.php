<?php declare(strict_types=1);

namespace Shopware\Api\Test\Context\Repository;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Api\Category\Definition\CategoryDefinition;
use Shopware\Api\Category\Event\Category\CategoryDeletedEvent;
use Shopware\Api\Context\Repository\ContextRuleRepository;
use Shopware\Api\Entity\RepositoryInterface;
use Shopware\Api\Entity\Write\GenericWrittenEvent;
use Shopware\Cart\Test\Common\FalseRule;
use Shopware\Cart\Test\Common\TrueRule;
use Shopware\Context\Rule\Container\AndRule;
use Shopware\Context\Rule\Container\OrRule;
use Shopware\Context\Struct\ShopContext;
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
     * @var ShopContext
     */
    private $context;

    public function setUp()
    {
        self::bootKernel();
        $this->container = self::$kernel->getContainer();
        $this->repository = $this->container->get(ContextRuleRepository::class);
        $this->connection = $this->container->get(Connection::class);
        $this->context = ShopContext::createDefaultContext();
        $this->connection->beginTransaction();
    }

    public function tearDown(): void
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function testWriteContextRuleWithObject()
    {
        $id = Uuid::uuid4()->toString();

        $data = [
            'id' => $id,
            'name' => 'test rule',
            'payload' => new AndRule([new OrRule()])
        ];

        $this->repository->create([$data], $this->context);

        $rules = $this->repository->readBasic([$id], $this->context);

        $this->assertEquals(
            new AndRule([new OrRule()]),
            $rules->get($id)->getPayload()
        );
    }
}
