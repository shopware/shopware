<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class RepositoryRegistry
{
    /**
     * @var EntityRepositoryInterface[]
     */
    private $repositories;

    /**
     * @var EntityReaderInterface
     */
    private $reader;

    /**
     * @var VersionManager
     */
    private $versionManager;

    /**
     * @var EntitySearcherInterface
     */
    private $searcher;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var EntityAggregatorInterface
     */
    private $aggregator;

    public function __construct(
        array $repositories,
        EntityReaderInterface $reader,
        VersionManager $versionManager,
        EntitySearcherInterface $searcher,
        EventDispatcherInterface $eventDispatcher,
        EntityAggregatorInterface $aggregator
    ) {
        $this->repositories = $repositories;
        $this->reader = $reader;
        $this->versionManager = $versionManager;
        $this->searcher = $searcher;
        $this->eventDispatcher = $eventDispatcher;
        $this->aggregator = $aggregator;
    }

    public function get(string $definition, int $version = 1)
    {
        for ($i = $version; $i >= 1; --$i) {
            if (array_key_exists($definition, $this->repositories)) {
                return $this->repositories[$definition];
            }
        }

        $repository = new EntityRepository(
            $definition,
            $this->reader,
            $this->versionManager,
            $this->searcher,
            $this->aggregator,
            $this->eventDispatcher
        );

        return $this->repositories[$version][$definition] = $repository;
    }
}
