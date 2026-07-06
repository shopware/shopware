<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Health;

use Shopware\Core\Framework\Log\Package;

/**
 * What tripped a webhook into SUSPENDED. The remedy differs per cause — rotate
 * credentials (auth streak), fix a retired URL (gone), or wait out recovery
 * (exhausted cooldown schedule) — so the vendor-facing suspension event carries
 * this as a coarse machine-readable enum. Never an error message or a count.
 *
 * A mixed episode (auth failures riding a transient ladder to its end) reports
 * `ScheduleExhausted`: a dominant auth streak would have tripped `AuthStreak`
 * on its own before the schedule ran out.
 *
 * @internal
 */
#[Package('framework')]
enum SuspensionCause: string
{
    case AuthStreak = 'auth_streak';
    case Gone = 'gone';
    case ScheduleExhausted = 'schedule_exhausted';
}
