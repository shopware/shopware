<?php declare(strict_types=1);

namespace Shopware\Traceable\Dbal;

use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\Search\Criteria;
use Shopware\Framework\ORM\Search\EntitySearcherInterface;
use Shopware\Framework\ORM\Search\IdSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
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

    public function search(string $definition, Criteria $criteria, ApplicationContext $context): IdSearchResult
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
