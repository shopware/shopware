<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Instrumentation;

use Shopware\Core\Framework\Log\Package;

/**
 * Shared vocabulary for the `result` label of instrumented operations, so every metric reports
 * failure the same way. Metrics with a domain-specific outcome (e.g. `mail.send.*` with `sent`)
 * keep their own values.
 *
 * The label values on the wire are the contract, not this class; it stays internal so the
 * vocabulary can still grow freely and may be opened up later if plugins need it.
 *
 * @internal
 *
 * @codeCoverageIgnore - value holder
 */
#[Package('framework')]
abstract class OperationResult
{
    public const SUCCESS = 'success';
    public const FAILED = 'failed';

    public static function fromOutcome(?\Throwable $throwable): string
    {
        return $throwable === null ? self::SUCCESS : self::FAILED;
    }
}
