<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

class MethodLevelIgnoreOnLogicClass
{
    public function pureGetter(): string
    {
        return 'name';
    }

    /**
     * @codeCoverageIgnore
     */
    public function withLogic(int $value): int
    {
        foreach (range(0, $value) as $i) {
            if ($i % 2 === 0) {
                $value += $i;
            }
        }

        return $value;
    }
}
