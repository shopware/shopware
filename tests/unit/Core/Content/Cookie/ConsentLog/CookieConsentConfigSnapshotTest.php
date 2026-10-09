<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\ConsentLog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentConfigSnapshot;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CookieConsentConfigSnapshot::class)]
class CookieConsentConfigSnapshotTest extends TestCase
{
    public function testASnapshotSerializesTheCookieGroupsAsAList(): void
    {
        $snapshot = new CookieConsentConfigSnapshot(
            configHash: 'hash',
            cookieGroups: new CookieGroupCollection(['required' => new CookieGroup('cookie.groupRequired')]),
            createdAt: new \DateTimeImmutable('2026-07-13 12:00:00', new \DateTimeZone('UTC')),
        );

        static::assertSame([
            'configHash' => 'hash',
            'cookieGroups' => [['extensions' => [], 'isRequired' => false, 'name' => 'cookie.groupRequired', 'technicalName' => 'cookie.groupRequired']],
            'createdAt' => '2026-07-13T12:00:00.000+00:00',
        ], json_decode((string) json_encode($snapshot), true, 512, \JSON_THROW_ON_ERROR));
    }
}
