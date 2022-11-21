<?php declare(strict_types=1);

namespace Shopware\Core\System\Test;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait EntityFixturesBase
{
    /**
     * @var Context
     */
    private $entityFixtureContext;

    /**
     * @before
     * Resets the context before each test
     */
    public function initializeFixtureContext(): void
    {
        $this->entityFixtureContext = Context::createDefaultContext();
    }

    public function setFixtureContext(Context $context): void
    {
        $this->entityFixtureContext = $context;
    }

    public static function getFixtureRepository(string $fixtureName): EntityRepository
    {
        self::ensureATransactionIsActive();

        $container = KernelLifecycleManager::getKernel()->getContainer();

        if ($container->has('test.service_container')) {
            /** @var ContainerInterface $container */
            $container = $container->get('test.service_container');
        }

        return $container->get(DefinitionInstanceRegistry::class)->getRepository($fixtureName);
    }

    /**
     * @param array<string, array<string, mixed>> $fixtureData
     */
    public function createFixture(string $fixtureName, array $fixtureData, EntityRepository $repository): Entity
    {
        self::ensureATransactionIsActive();

        $repository->create([$fixtureData[$fixtureName]], $this->entityFixtureContext);

        if (\array_key_exists('mediaType', $fixtureData[$fixtureName])) {
            $connection = KernelLifecycleManager::getKernel()
                ->getContainer()
                ->get(Connection::class);
            $connection->update(
                'media',
                [
                    'media_type' => serialize($fixtureData[$fixtureName]['mediaType']),
                ],
                ['id' => Uuid::fromHexToBytes($fixtureData[$fixtureName]['id'])]
            );
        }

        $criteria = new Criteria([$fixtureData[$fixtureName]['id']]);

        $entity = $repository
            ->search($criteria, $this->entityFixtureContext)
            ->get($fixtureData[$fixtureName]['id']);

        static::assertInstanceOf(Entity::class, $entity);

        return $entity;
    }

    private static function ensureATransactionIsActive(): void
    {
        $connection = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class);

        if (!$connection->isTransactionActive()) {
            throw new \BadMethodCallException('You should not start writing to the database outside of transactions');
        }
    }
}
