<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\Docs\Script\_fixtures\hooks;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Script\Execution\Hook;

/**
 * Triggered when something simple happens.
 *
 * @hook-use-case data_loading
 *
 * @since 6.4.0.0
 */
class SimpleHook extends Hook
{
    final public const HOOK_NAME = 'simple-hook';

    public string $simpleProperty = 'value';

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
