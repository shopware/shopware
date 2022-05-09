<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Entity;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * @deprecated tag:v6.5.0 - reason:remove-decorator - Will be removed, use the static Profiler::trace method to directly trace functions
 */
class EntityReaderProfiler implements EntityReaderInterface
{
    private EntityReaderInterface $decorated;

    /**
     * @internal
     */
    public function __construct(EntityReaderInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    public function read(EntityDefinition $definition, Criteria $criteria, Context $context): EntityCollection
    {
        return $this->decorated->read($definition, $criteria, $context);
    }
}
