<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

/**
 * Fixture: an exception whose only branching selects between two exception shapes
 * (the feature-flag fork pattern). Inside \Throwable subclasses plain conditionals
 * are not logic, so the class-level ignore must pass.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
class ExceptionForkClass extends \RuntimeException
{
    public static function somethingBroke(bool $legacy): \Throwable
    {
        if ($legacy) {
            return new \LogicException('legacy shape');
        }

        return new self('new shape');
    }

    public static function variantMessage(?string $code): self
    {
        return new self($code === null ? 'automated' : 'coded');
    }
}
