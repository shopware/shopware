<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\ConsentLog;

use Shopware\Core\Framework\Log\Package;

/**
 * Keeps the cookie consent log. Tag an implementation with `shopware.cookie_consent.log_storage`
 * and a `storage` name to select it via the `shopware.cookie_consent.log_storage` config.
 */
#[Package('framework')]
interface CookieConsentLogStorageInterface
{
    /**
     * Stores one consent decision
     */
    public function log(CookieConsentRecord $record): void;

    /**
     * Stores the banner configuration a decision refers to, once per configuration hash
     */
    public function snapshot(CookieConsentConfigSnapshot $snapshot): void;

    /**
     * Deletes the decisions recorded before the given time
     */
    public function cleanup(\DateTimeInterface $before): void;

    /**
     * Returns the decisions recorded from `$from` (inclusive) to `$to` (exclusive), oldest first
     *
     * @return iterable<CookieConsentRecord>
     */
    public function iterate(\DateTimeInterface $from, \DateTimeInterface $to, ?string $salesChannelId = null): iterable;
}
