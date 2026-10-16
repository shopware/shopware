<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry\Instrumentation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Instrumentation\OperationResult;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OperationResult::class)]
class OperationResultTest extends TestCase
{
    public function testOutcomeWithoutExceptionIsSuccess(): void
    {
        static::assertSame(OperationResult::SUCCESS, OperationResult::fromOutcome(null));
    }

    public function testOutcomeWithExceptionIsFailed(): void
    {
        static::assertSame(OperationResult::FAILED, OperationResult::fromOutcome(new \RuntimeException('instrumented operation failed')));
    }
}
