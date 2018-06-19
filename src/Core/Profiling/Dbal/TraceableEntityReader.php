<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Dbal;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Read\EntityReaderInterface;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Symfony\Component\Stopwatch\Stopwatch;

class TraceableEntityReader implements EntityReaderInterface
{
    /**
     * @var EntityReaderInterface
     */
    private $decorated;

    /**
     * @var Stopwatch
     */
    private $stopwatch;

    public function __construct(EntityReaderInterface $decorated, Stopwatch $stopwatch)
    {
        $this->decorated = $decorated;
        $this->stopwatch = $stopwatch;
    }

    public function readRaw(string $definition, ReadCriteria $criteria, Context $context): EntityCollection
    {
        /** @var EntityDefinition $definition */
        $entity = $definition::getEntityName();

        $e = $this->stopwatch->start($entity . '.read_raw', 'shopware');

        $result = $this->decorated->readRaw($definition, $criteria, $context);

        if ($e->isStarted()) {
            $e->stop();
        }

        return $result;
    }

    public function read(string $definition, ReadCriteria $criteria, Context $context): EntityCollection
    {
        /** @var EntityDefinition $definition */
        $entity = $definition::getEntityName();

        $e = $this->stopwatch->start($entity . '.read_basic', 'section');

        $result = $this->decorated->read($definition, $criteria, $context);

        if ($e->isStarted()) {
            $e->stop();
        }

        return $result;
    }
}
