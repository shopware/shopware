<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

/**
 * @codeCoverageIgnore
 */
class BinaryOpClass
{
    public function sum(int $a, int $b): int
    {
        return $a + $b;
    }
}
