<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Health;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Webhook\Health\SuspensionCause;

/**
 * @internal
 */
#[CoversClass(SuspensionCause::class)]
class SuspensionCauseTest extends TestCase
{
    public function testValues(): void
    {
        static::assertSame(
            ['auth_streak', 'gone', 'schedule_exhausted'],
            array_column(SuspensionCause::cases(), 'value'),
        );
    }
}
