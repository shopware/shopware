<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Entity;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Symfony\Component\Stopwatch\Stopwatch;

class EntitySearcherProfiler implements EntitySearcherInterface
{
    /**
     * @var EntitySearcherInterface
     */
    private $decorated;

    /**
     * @var Stopwatch
     */
    private $stopwatch;

    public function __construct(EntitySearcherInterface $decorated, Stopwatch $stopwatch)
    {
        $this->decorated = $decorated;
        $this->stopwatch = $stopwatch;
    }

    public function search(EntityDefinition $definition, Criteria $criteria, Context $context): IdSearchResult
    {
        $title = $criteria->getTitle() ?? $definition->getEntityName();

        $this->stopwatch->start('search:' . $title);

        $data = $this->decorated->search($definition, $criteria, $context);

        $this->stopwatch->stop('search:' . $title);

        return $data;
    }
}
