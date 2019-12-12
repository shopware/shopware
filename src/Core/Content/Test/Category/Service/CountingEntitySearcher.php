<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Category\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

class CountingEntitySearcher implements EntitySearcherInterface
{
    /**
     * @var int[]
     */
    private static $count = [];

    /**
     * @var EntitySearcherInterface
     */
    private $inner;

    public function __construct(EntitySearcherInterface $inner)
    {
        $this->inner = $inner;
    }

    public function search(EntityDefinition $definition, Criteria $criteria, Context $context): IdSearchResult
    {
        static::$count[$definition->getEntityName()] = static::$count[$definition->getEntityName()] ?? 0 + 1;

        return $this->inner->search($definition, $criteria, $context);
    }

    public static function resetCount(): void
    {
        static::$count = [];
    }

    public static function getSearchOperationCount(string $entityName): int
    {
        return static::$count[$entityName] ?? 0;
    }
}
