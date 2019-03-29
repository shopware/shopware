<?php declare(strict_types=1);

namespace Shopware\Core\System\Test;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;

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

    public static function getFixtureRepository(string $fixtureName): EntityRepositoryInterface
    {
        self::ensureATransactionIsActive();

        $container = KernelLifecycleManager::getKernel()->getContainer();

        if ($container->has('test.service_container')) {
            $container = $container->get('test.service_container');
        }

        return $container->get(DefinitionRegistry::class)->getRepository($fixtureName);
    }

    public function createFixture(string $fixtureName, array $fixtureData, EntityRepositoryInterface $repository): Entity
    {
        self::ensureATransactionIsActive();

        $repository->create([$fixtureData[$fixtureName]], $this->entityFixtureContext);

        if (array_key_exists('mediaType', $fixtureData[$fixtureName])) {
            $connection = KernelLifecycleManager::getKernel()
                ->getContainer()
                ->get(Connection::class);
            $connection->update(
                MediaDefinition::getEntityName(),
                [
                    'media_type' => serialize($fixtureData[$fixtureName]['mediaType']),
                ],
                ['id' => Uuid::fromHexToBytes($fixtureData[$fixtureName]['id'])]
            );
        }

        return $repository->search(new Criteria([$fixtureData[$fixtureName]['id']]), $this->entityFixtureContext)
            ->get($fixtureData[$fixtureName]['id']);
    }

    private static function ensureATransactionIsActive(): void
    {
        /** @var Connection $connection */
        $connection = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class);

        if (!$connection->isTransactionActive()) {
            throw new \BadMethodCallException('You should not start writing to the database outside of transactions');
        }
    }
}
