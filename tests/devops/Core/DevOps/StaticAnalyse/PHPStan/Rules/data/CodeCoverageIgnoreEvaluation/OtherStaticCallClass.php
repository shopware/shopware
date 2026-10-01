<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

/**
 * @codeCoverageIgnore
 */
class OtherStaticCallClass
{
    public function format(string $value): string
    {
        return \sprintf('[%s]', $value);
    }
}
