<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

/**
 * @codeCoverageIgnore
 */
class PromotedConstructorClass
{
    public function __construct(
        public readonly string $name,
        public readonly int $age,
    ) {
    }
}
