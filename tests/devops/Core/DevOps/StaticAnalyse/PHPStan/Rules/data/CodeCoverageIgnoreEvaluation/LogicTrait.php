<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

trait LogicTrait
{
    public function doSomething(int $value): int
    {
        if ($value < 0) {
            return 0;
        }

        return $value * 2;
    }
}
