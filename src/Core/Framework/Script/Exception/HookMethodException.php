<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

/**
 * @ deprecated tag:v6.6.0 - Will be removed. Use Shopware\Core\Framework\Script\ScriptException instead
 */
#[Package('core')]
class HookMethodException extends ShopwareHttpException
{
    public static function outsideOfSalesChannelContext(string $method): self
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.6.0.0', 'Use Shopware\Core\Framework\Script\ScriptException instead')
        );

        return new self(sprintf(
            'Method "%s" can only be called from inside the `SalesChannelContext`.',
            $method
        ));
    }

    public static function storefrontBundleMissing(string $method): self
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.6.0.0', 'Use Shopware\Core\Framework\Script\ScriptException instead')
        );

        return new self(sprintf(
            'Method "%s" can only be called if the `storefront`-bundle is installed.',
            $method
        ));
    }

    public static function accessFromScriptExecutionContextNotAllowed(string $class, string $method): self
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.6.0.0', 'Use Shopware\Core\Framework\Script\ScriptException instead')
        );

        return new self(sprintf(
            'Method "%s" of class "%s" can not be called from inside a script.',
            $method,
            $class
        ));
    }

    public static function functionDoesNotExistInInterfaceHook(string $class, string $function): self
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.6.0.0', 'Use Shopware\Core\Framework\Script\ScriptException instead')
        );

        return new self(sprintf(
            'Function "%s" does not exist for InterfaceHook "%s".',
            $function,
            $class
        ));
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__HOOK_METHOD_EXCEPTION';
    }
}
