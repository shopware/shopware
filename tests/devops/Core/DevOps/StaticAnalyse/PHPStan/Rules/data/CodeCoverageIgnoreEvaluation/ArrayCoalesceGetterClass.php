<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

/**
 * @codeCoverageIgnore
 */
class ArrayCoalesceGetterClass
{
    /**
     * @var array<string, object>
     */
    private array $searchResults = [];

    public function getResult(string $entityName): ?object
    {
        return $this->searchResults[$entityName] ?? null;
    }
}
