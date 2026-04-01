<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @internal
 */
#[Package('framework')]
enum WebhookFailureStrategy: string
{
    case DisableOnThreshold = 'disable_on_threshold';
    case Ignore = 'ignore';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
