<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\ConsentLog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentAction;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentDecision;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentRecord;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CookieConsentRecord::class)]
class CookieConsentRecordTest extends TestCase
{
    public function testARecordSerializesToPlainValues(): void
    {
        $record = new CookieConsentRecord(
            consentId: 'consent-id',
            consentAction: CookieConsentAction::ACCEPT_SELECTED,
            groupDecisions: ['cookie.groupStatistical' => CookieConsentDecision::PARTIAL],
            acceptedCookies: ['lorem'],
            configHash: 'hash',
            salesChannelId: 'sales-channel-id',
            languageId: 'language-id',
            createdAt: new \DateTimeImmutable('2026-07-13 12:00:00.123', new \DateTimeZone('UTC')),
        );

        static::assertSame([
            'consentId' => 'consent-id',
            'consentAction' => 'accept_selected',
            'groupDecisions' => ['cookie.groupStatistical' => 'partial'],
            'acceptedCookies' => ['lorem'],
            'configHash' => 'hash',
            'salesChannelId' => 'sales-channel-id',
            'languageId' => 'language-id',
            'createdAt' => '2026-07-13T12:00:00.123+00:00',
        ], $record->jsonSerialize());
    }
}
