<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Health;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Webhook\Health\ErrorClassification;

/**
 * @internal
 */
#[CoversClass(ErrorClassification::class)]
class ErrorClassificationTest extends TestCase
{
    public function testOnlyTransientFailuresAreTransient(): void
    {
        $transient = array_values(array_filter(
            ErrorClassification::cases(),
            static fn (ErrorClassification $classification): bool => $classification->isTransient(),
        ));

        static::assertEqualsCanonicalizing([
            ErrorClassification::TransientNetwork,
            ErrorClassification::TransientServer,
            ErrorClassification::TransientRateLimit,
            ErrorClassification::TransientRedirect,
        ], $transient);
    }
}
