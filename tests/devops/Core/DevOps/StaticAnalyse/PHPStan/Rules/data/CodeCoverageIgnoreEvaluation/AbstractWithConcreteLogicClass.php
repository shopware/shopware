<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

/**
 * @codeCoverageIgnore
 */
abstract class AbstractWithConcreteLogicClass
{
    abstract public function load(): string;

    public function decorate(string $value): string
    {
        if ($value === '') {
            return '<empty>';
        }

        return '<' . $value . '>';
    }
}
