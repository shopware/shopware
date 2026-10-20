<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation;

use Shopware\Core\Framework\Deprecation\ClassAliasRegistry;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class ClassAliasMap
{
    /**
     * @var array<lowercase-string, class-string>
     */
    private array $classAliases = [];

    /**
     * @var array<lowercase-string, list<non-empty-string>>
     */
    private array $aliasesByCanonicalClassName = [];

    /**
     * @param array<non-empty-string, class-string> $classAliases
     */
    public function __construct(array $classAliases = ClassAliasRegistry::ALIASES)
    {
        foreach ($classAliases as $previousClassName => $currentClassName) {
            $this->classAliases[\strtolower($previousClassName)] = $currentClassName;
            $this->aliasesByCanonicalClassName[\strtolower($currentClassName)][] = $previousClassName;
        }
    }

    /**
     * @return class-string|null
     */
    public function canonicalClassName(string $className): ?string
    {
        return $this->classAliases[\strtolower($className)] ?? null;
    }

    /**
     * @return list<non-empty-string>
     */
    public function aliasesForCanonicalClassName(string $className): array
    {
        return $this->aliasesByCanonicalClassName[\strtolower($className)] ?? [];
    }
}
