<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\ConsentLog;

use Shopware\Core\Framework\Log\Package;

/**
 * Where cookie consent decisions are kept.
 *
 * The shipped default writes to the shop database. The log is write-once evidence
 * nobody reads in normal operation, so a shop with a lot of traffic can keep it out
 * of its primary database: implement this class, tag the service with
 * `shopware.cookie_consent.log_storage` and select it via
 * `shopware.cookie_consent.log_storage` in the bundle configuration.
 *
 * @experimental stableVersion:v6.8.0 feature:COOKIE_GROUPS_STORE_API
 */
#[Package('framework')]
abstract class AbstractCookieConsentLogStorage
{
    /**
     * Persists one consent decision
     */
    abstract public function log(CookieConsentRecord $record): void;

    /**
     * Persists the banner configuration a decision refers to. Called before every
     * `log()`, so it has to be cheap when the hash is already known.
     */
    abstract public function snapshot(CookieConsentConfigSnapshot $snapshot): void;

    /**
     * Deletes decisions recorded before the given point in time
     */
    abstract public function cleanup(\DateTimeImmutable $before): void;

    /**
     * Decisions recorded from `$from` (inclusive) to `$to` (exclusive), oldest first
     *
     * @return iterable<CookieConsentRecord>
     */
    abstract public function iterate(\DateTimeImmutable $from, \DateTimeImmutable $to, ?string $salesChannelId = null): iterable;
}
