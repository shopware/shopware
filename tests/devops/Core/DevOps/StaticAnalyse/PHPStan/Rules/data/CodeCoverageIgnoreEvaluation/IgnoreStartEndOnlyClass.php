<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

class IgnoreStartEndOnlyClass
{
    public function describe(int $age): string
    {
        // @codeCoverageIgnoreStart
        if ($age < 0) {
            throw new \InvalidArgumentException('age');
        }
        // @codeCoverageIgnoreEnd

        return (string) $age;
    }
}
