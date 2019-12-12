<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Category\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class CountingEntityReader implements EntityReaderInterface
{
    /**
     * @var int[]
     */
    private static $count = [];

    /**
     * @var EntityReaderInterface
     */
    private $inner;

    public function __construct(EntityReaderInterface $inner)
    {
        $this->inner = $inner;
    }

    public function read(EntityDefinition $definition, Criteria $criteria, Context $context): EntityCollection
    {
        static::$count[$definition->getEntityName()] = static::$count[$definition->getEntityName()] ?? 0 + 1;

        return $this->inner->read($definition, $criteria, $context);
    }

    public static function resetCount(): void
    {
        static::$count = [];
    }

    public static function getReadOperationCount(string $entityName): int
    {
        return static::$count[$entityName] ?? 0;
    }
}
