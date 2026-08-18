<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\ContextHandoffTokenResponse;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContextHandoffTokenResponse::class)]
class ContextHandoffTokenResponseTest extends TestCase
{
    public function testHandoffTokenAndExpiryAreExposed(): void
    {
        $response = new ContextHandoffTokenResponse(
            'the-handoff-token',
            new \DateTimeImmutable('2026-08-18T12:01:00+00:00')
        );

        static::assertSame('the-handoff-token', $response->getHandoffToken());
        static::assertSame('2026-08-18T12:01:00+00:00', $response->getExpiresAt());
    }

    public function testResponseObjectOnlyCarriesTheTokenAndItsExpiry(): void
    {
        $response = new ContextHandoffTokenResponse(
            'the-handoff-token',
            new \DateTimeImmutable('2026-08-18T12:01:00+00:00')
        );

        static::assertSame(
            ['token' => 'the-handoff-token', 'expiresAt' => '2026-08-18T12:01:00+00:00'],
            $response->getObject()->all()
        );
    }
}
