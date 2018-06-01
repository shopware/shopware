<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Dbal;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Read\EntityReaderInterface;
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

    public function readRaw(string $definition, array $ids, Context $context): EntityCollection
    {
        /** @var EntityDefinition $definition */
        $entity = $definition::getEntityName();

        $e = $this->stopwatch->start($entity . '.read_raw', 'shopware');

        $result = $this->decorated->readRaw($definition, $ids, $context);

        if ($e->isStarted()) {
            $e->stop();
        }

        return $result;
    }

    public function readDetail(string $definition, array $ids, Context $context): EntityCollection
    {
        /** @var EntityDefinition $definition */
        $entity = $definition::getEntityName();

        $e = $this->stopwatch->start($entity . '.read_detail', 'shopware');

        $result = $this->decorated->readDetail($definition, $ids, $context);

        if ($e->isStarted()) {
            $e->stop();
        }

        return $result;
    }

    public function readBasic(string $definition, array $ids, Context $context): EntityCollection
    {
        /** @var EntityDefinition $definition */
        $entity = $definition::getEntityName();

        $e = $this->stopwatch->start($entity . '.read_basic', 'section');

        $result = $this->decorated->readBasic($definition, $ids, $context);

        if ($e->isStarted()) {
            $e->stop();
        }

        return $result;
    }
}
