<?php declare(strict_types=1);

namespace Shopware\Core\System\Test;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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

        return KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get($fixtureName . '.repository');
    }

    /**
     * @return Entity
     */
    public function createFixture(string $fixtureName, array $fixtureData, EntityRepositoryInterface $repository): Entity
    {
        self::ensureATransactionIsActive();

        $repository->create([$fixtureData[$fixtureName]], $this->entityFixtureContext);

        return $repository->read(new Criteria([$fixtureData[$fixtureName]['id']]), $this->entityFixtureContext)
            ->get($fixtureData[$fixtureName]['id']);
    }

    private static function ensureATransactionIsActive()
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
