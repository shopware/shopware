<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\Docs\Script\_fixtures\hooks;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Script\Execution\FunctionHook;

/**
 * Triggered for the response function.
 *
 * @hook-use-case custom_endpoint
 *
 * @since 6.4.9.0
 */
class SimpleResponseFunctionHook extends FunctionHook
{
    final public const FUNCTION_NAME = 'simple_response';

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
}
