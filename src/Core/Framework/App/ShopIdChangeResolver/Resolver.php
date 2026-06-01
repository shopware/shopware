<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ShopIdChangeResolver;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Event\ShopIdResolvedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
readonly class Resolver
{
    /**
     * @param AbstractShopIdChangeStrategy[] $strategies
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private iterable $strategies,
        private EntityRepository $appRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function resolve(string $strategyName, Context $context): void
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->getName() === $strategyName) {
                // Snapshot the installed apps BEFORE running the strategy: the uninstall strategy deletes the rows,
                // so listeners reacting to the resulting event would otherwise lose the technical names they need.
                $affectedApps = $this->snapshotInstalledApps($context);

                $strategy->resolve($context);

                $this->eventDispatcher->dispatch(
                    new ShopIdResolvedEvent($strategyName, $affectedApps, $context)
                );

                return;
            }
        }

        throw AppException::shopIdChangeResolveStrategyNotFound($strategyName);
    }

    /**
     * @return array<string>
     */
    public function getAvailableStrategies(): array
    {
        $strategies = [];

        foreach ($this->strategies as $strategy) {
            $strategies[$strategy->getName()] = $strategy->getDescription();
        }

        return $strategies;
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    private function snapshotInstalledApps(Context $context): array
    {
        $snapshot = [];
        foreach ($this->appRepository->search(new Criteria(), $context)->getEntities() as $app) {
            $snapshot[] = ['id' => $app->getId(), 'name' => $app->getName()];
        }

        return $snapshot;
    }
}
