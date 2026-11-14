<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Health;

use Shopware\Core\Framework\Log\Package;

/**
 * Machine-readable reason for suspending a webhook.
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
