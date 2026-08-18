<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

/**
 * Fixture: an exception that aggregates inner errors with a loop. Loops stay logic
 * even inside \Throwable subclasses, so the class-level ignore must fail.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
class ExceptionWithAggregationClass extends \RuntimeException
{
    /**
     * @param list<string> $messages
     */
    public static function aggregate(array $messages): self
    {
        $joined = '';
        foreach ($messages as $message) {
            $joined .= $message;
        }

        return new self($joined);
    }
}
