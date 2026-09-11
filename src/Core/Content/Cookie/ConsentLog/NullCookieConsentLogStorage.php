<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\ConsentLog;

use Shopware\Core\Framework\Log\Package;

/**
 * Discards every decision. Selected with `shopware.cookie_consent.log_storage: none`,
 * e.g. when a third-party consent manager keeps the record instead.
 *
 * @internal
 */
#[Package('framework')]
final class NullCookieConsentLogStorage extends AbstractCookieConsentLogStorage
{
    public function log(CookieConsentRecord $record): void
    {
    }

    public function snapshot(CookieConsentConfigSnapshot $snapshot): void
    {
    }

    public function cleanup(\DateTimeImmutable $before): void
    {
    }

    public function findByConsentId(string $consentId): array
    {
        return [];
    }

    public function findSnapshot(string $configHash): ?CookieConsentConfigSnapshot
    {
        return null;
    }

    public function iterate(\DateTimeImmutable $from, \DateTimeImmutable $to, ?string $salesChannelId = null): iterable
    {
        return [];
    }
}
