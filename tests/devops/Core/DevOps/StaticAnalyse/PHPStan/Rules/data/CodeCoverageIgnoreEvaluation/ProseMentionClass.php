<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

/**
 * Explains that classes annotated with `@codeCoverageIgnore` stay valid coverage targets:
 * a docblock that merely MENTIONS the annotation in prose does not carry it.
 */
class ProseMentionClass
{
    public function describe(string $value): string
    {
        if ($value === '') {
            return 'empty';
        }

        return $value;
    }
}
