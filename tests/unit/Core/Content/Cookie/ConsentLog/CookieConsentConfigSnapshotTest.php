<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\ConsentLog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentConfigSnapshot;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CookieConsentConfigSnapshot::class)]
class CookieConsentConfigSnapshotTest extends TestCase
{
    public function testASnapshotSerializesToPlainValues(): void
    {
        $snapshot = new CookieConsentConfigSnapshot(
            configHash: 'hash',
            cookieGroups: [['technicalName' => 'cookie.groupRequired']],
            createdAt: new \DateTimeImmutable('2026-07-13 12:00:00', new \DateTimeZone('UTC')),
        );

        static::assertSame([
            'configHash' => 'hash',
            'cookieGroups' => [['technicalName' => 'cookie.groupRequired']],
            'createdAt' => '2026-07-13T12:00:00.000+00:00',
        ], $snapshot->jsonSerialize());
    }
}
