<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\Docs\Script\_fixtures\hooks;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Script\Execution\Hook;

/**
 * Hook with a valid service factory.
 *
 * @hook-use-case data_loading
 *
 * @since 6.4.0.0
 */
class HookWithService extends Hook
{
    final public const HOOK_NAME = 'hook-with-service';

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
        return [SimpleHookServiceFactory::class];
    }
}
