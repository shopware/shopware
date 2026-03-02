<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\Docs\Script\_fixtures\hooks;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Script\Execution\Hook;

/**
 * Hook with a @hook-use-case tag but no description value.
 *
 * @hook-use-case
 *
 * @since 6.4.0.0
 */
class HookWithEmptyUseCase extends Hook
{
    final public const HOOK_NAME = 'empty-use-case-hook';

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
}
