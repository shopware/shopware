<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

/**
 * @codeCoverageIgnore
 */
class CompoundAssignClass
{
    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $children
     *
     * @return array<string, mixed>
     */
    public function merge(array $attributes, array $children): array
    {
        $values = $attributes;
        $values += $children;

        return $values;
    }
}
