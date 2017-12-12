<?php declare(strict_types=1);

namespace Shopware\Traceable\Dbal;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\EntitySearcherInterface;
use Shopware\Api\Search\UuidSearchResult;
use Shopware\Context\Struct\TranslationContext;
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

    public function search(string $definition, Criteria $criteria, TranslationContext $context): UuidSearchResult
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
