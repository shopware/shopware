<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\ConsentLog;

use Shopware\Core\Framework\Log\Package;

/**
 * Discards every decision. The default: consent logging is opt-in, and a shop with a
 * third-party consent manager keeps it off.
 *
 * @internal
 */
#[Package('framework')]
final class NullCookieConsentLogStorage extends AbstractCookieConsentLogStorage
{
    public const NAME = 'none';

    public function log(CookieConsentRecord $record): void
    {
    }

    public function snapshot(CookieConsentConfigSnapshot $snapshot): void
    {
    }

    public function cleanup(\DateTimeImmutable $before): void
    {
    }

    public function iterate(\DateTimeImmutable $from, \DateTimeImmutable $to, ?string $salesChannelId = null): iterable
    {
        return [];
    }
}
