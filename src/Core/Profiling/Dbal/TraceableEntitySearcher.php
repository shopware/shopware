<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Dbal;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\EntitySearcherInterface;
use Shopware\Core\Framework\ORM\Search\IdSearchResult;
use Symfony\Component\Stopwatch\Stopwatch;

class TraceableEntitySearcher implements EntitySearcherInterface
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

    public function search(string $definition, Criteria $criteria, Context $context): IdSearchResult
    {
        /** @var EntityDefinition $definition */
        $entity = $definition::getEntityName();

        $e = $this->stopwatch->start($entity . '.search', 'shopware');

        $result = $this->decorated->search($definition, $criteria, $context);

        if ($e->isStarted()) {
            $e->stop();
        }

        return $result;
    }
}
