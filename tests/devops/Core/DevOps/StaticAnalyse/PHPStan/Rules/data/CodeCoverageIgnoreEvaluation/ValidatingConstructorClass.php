<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

/**
 * @codeCoverageIgnore
 */
class ValidatingConstructorClass
{
    public function __construct(public readonly string $name)
    {
        if ($name === '') {
            throw new \InvalidArgumentException('name required');
        }
    }
}
