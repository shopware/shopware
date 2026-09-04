<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Health;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Health\DisabledOrigin;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DisabledOrigin::class)]
class DisabledOriginTest extends TestCase
{
    public function testValues(): void
    {
        static::assertSame(
            ['operator', 'escalation'],
            array_column(DisabledOrigin::cases(), 'value'),
        );
    }
}
