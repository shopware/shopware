<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Entity;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\Stopwatch\Stopwatch;

class EntityReaderProfiler implements EntityReaderInterface
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

    public function read(EntityDefinition $definition, Criteria $criteria, Context $context): EntityCollection
    {
        $title = $criteria->getTitle() ?? $definition->getEntityName();

        $this->stopwatch->start('read:' . $title);

        $data = $this->decorated->read($definition, $criteria, $context);

        $this->stopwatch->stop('read:' . $title);

        return $data;
    }
}
