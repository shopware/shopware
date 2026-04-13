<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\Docs\Script\_fixtures\hooks;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Script\Execution\DeprecatedHook;
use Shopware\Core\Framework\Script\Execution\Hook;

/**
 * Triggered when something deprecated happens.
 *
 * @hook-use-case app_lifecycle
 *
 * @since 6.3.0.0
 */
class DeprecatedHookFixture extends Hook implements DeprecatedHook
{
    final public const HOOK_NAME = 'deprecated-hook';

    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public static function getServiceIds(): array
    {
        return [];
    }

    public static function getDeprecationNotice(): string
    {
        return 'Use something else instead.';
    }
}
