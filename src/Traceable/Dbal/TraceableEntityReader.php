<?php declare(strict_types=1);

namespace Shopware\Traceable\Dbal;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Read\EntityReaderInterface;
use Shopware\Context\Struct\TranslationContext;
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

    public function readDetail(string $definition, array $uuids, TranslationContext $context): EntityCollection
    {
        /** @var EntityDefinition $definition */
        $entity = $definition::getEntityName();

        $e = $this->stopwatch->start($entity . '.read_detail', 'shopware');

        $result = $this->decorated->readDetail($definition, $uuids, $context);

        if ($e->isStarted()) {
            $e->stop();
        }

        return $result;
    }

    public function readBasic(string $definition, array $uuids, TranslationContext $context): EntityCollection
    {
        /** @var EntityDefinition $definition */
        $entity = $definition::getEntityName();

        $e = $this->stopwatch->start($entity . '.read_basic', 'shopware');

        $result = $this->decorated->readBasic($definition, $uuids, $context);

        if ($e->isStarted()) {
            $e->stop();
        }

        return $result;
    }
}
