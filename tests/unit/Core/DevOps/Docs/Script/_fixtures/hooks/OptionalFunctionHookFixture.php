<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\Docs\Script\_fixtures\hooks;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Script\Execution\OptionalFunctionHook;

/**
 * Triggered for the optional function.
 *
 * @hook-use-case data_loading
 *
 * @since 6.5.0.0
 */
class OptionalFunctionHookFixture extends OptionalFunctionHook
{
    final public const FUNCTION_NAME = 'optional_function';

    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    public function getFunctionName(): string
    {
        return self::FUNCTION_NAME;
    }

    public function getName(): string
    {
        return self::FUNCTION_NAME;
    }

    public static function getServiceIds(): array
    {
        return [];
    }

    public static function willBeRequiredInVersion(): ?string
    {
        return '6.6.0.0';
    }
}
