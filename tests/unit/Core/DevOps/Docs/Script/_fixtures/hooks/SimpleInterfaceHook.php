<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\Docs\Script\_fixtures\hooks;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Script\Execution\FunctionHook;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\InterfaceHook;

/**
 * Triggered when an interface hook is called.
 *
 * @hook-use-case custom_endpoint
 *
 * @since 6.4.9.0
 */
class SimpleInterfaceHook extends InterfaceHook
{
    final public const HOOK_NAME = 'simple-interface-hook';

    final public const FUNCTIONS = [
        SimpleResponseFunctionHook::FUNCTION_NAME => SimpleResponseFunctionHook::class,
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
        return new SimpleResponseFunctionHook($this->context);
    }
}
