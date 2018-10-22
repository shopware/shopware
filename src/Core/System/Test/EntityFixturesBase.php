<?php declare(strict_types=1);

namespace Shopware\Core\System\Test;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\RepositoryInterface;
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
        $this->entityFixtureContext = Context::createDefaultContext(Defaults::TENANT_ID);
    }

    public function setFixtureContext(Context $context): void
    {
        $this->entityFixtureContext = $context;
    }

    public static function getFixtureRepository(string $fixtureName): RepositoryInterface
    {
        return KernelLifecycleManager::getKernel()->getContainer()->get($fixtureName . '.repository');
    }

    /**
     * @return Entity
     */
    public function createFixture(string $fixtureName, array $fixtureData, RepositoryInterface $repository): Entity
    {
        $repository->create([$fixtureData[$fixtureName]],
            $this->entityFixtureContext);

        return $repository->read(new ReadCriteria([$fixtureData[$fixtureName]['id']]), $this->entityFixtureContext)
            ->get($fixtureData[$fixtureName]['id']);
    }
}
