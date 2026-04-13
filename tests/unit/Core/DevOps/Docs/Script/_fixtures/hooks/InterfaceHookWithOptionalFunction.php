<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\Docs\Script\_fixtures\hooks;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Script\Execution\FunctionHook;
use Shopware\Core\Framework\Script\Execution\InterfaceHook;

/**
 * Triggered when an interface hook with optional functions is called.
 *
 * @hook-use-case data_loading
 *
 * @since 6.5.0.0
 */
class InterfaceHookWithOptionalFunction extends InterfaceHook
{
    final public const HOOK_NAME = 'interface-hook-with-optional';

    final public const FUNCTIONS = [
        OptionalFunctionHookFixture::FUNCTION_NAME => OptionalFunctionHookFixture::class,
    ];

    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getFunction(string $name): FunctionHook
    {
        return new OptionalFunctionHookFixture($this->context);
    }
}
