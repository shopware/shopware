<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

/**
 * @codeCoverageIgnore
 */
class ExceptionWithLogicClass extends \RuntimeException
{
    public function describe(int $code): string
    {
        if ($code === 0) {
            return 'no code';
        }

        return 'code: ' . $code;
    }
}
