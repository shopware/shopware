<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\ConsentLog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentAction;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentConfigSnapshot;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentRecord;
use Shopware\Core\Content\Cookie\ConsentLog\NullCookieConsentLogStorage;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NullCookieConsentLogStorage::class)]
class NullCookieConsentLogStorageTest extends TestCase
{
    public function testItDiscardsEverything(): void
    {
        $storage = new NullCookieConsentLogStorage();
        $now = new \DateTimeImmutable('2026-07-13 12:00:00');

        $storage->snapshot(new CookieConsentConfigSnapshot('hash', [], $now));
        $storage->log(new CookieConsentRecord(
            consentId: 'consent-id',
            consentAction: CookieConsentAction::ACCEPT_ALL,
            groupDecisions: [],
            acceptedCookies: [],
            configHash: 'hash',
            salesChannelId: 'sales-channel-id',
            languageId: 'language-id',
            createdAt: $now,
        ));
        $storage->cleanup($now);

        static::assertSame([], [...$storage->iterate(new \DateTimeImmutable('2020-01-01'), new \DateTimeImmutable('2030-01-01'))]);
    }
}
