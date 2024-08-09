<?php declare(strict_types=1);

namespace Shopware\Core\Framework\SystemCheck\Check;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('core')]
enum SystemCheckExecutionContext: string
{
    case WEB = 'web';

    case CLI = 'cli';

    case PRE_ROLLOUT = 'pre_rollout';

    case RECURRENT = 'recurrent';

    /**
     * @return array<self>
     */
    public static function readiness(): array
    {
        return [self::PRE_ROLLOUT];
    }

    /**
     * @return array<self>
     */
    public static function longRunning(): array
    {
        return [self::CLI, self::RECURRENT, self::PRE_ROLLOUT];
    }
}
