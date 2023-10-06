<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only rely on the concrete hook implementations
 */
#[Package('core')]
abstract class Hook
{
    protected Context $context;

    /**
     * @internal
     */
    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    /**
     * The services returned here must all extend the abstract HookServiceFactory.
     * These are then available in the script under the variable `services`.
     *
     * @internal
     *
     * @return list<class-string<object>>
     */
    abstract public static function getServiceIds(): array;

    /**
     * @internal
     */
    abstract public function getName(): string;

    /**
     * If a service will be removed from a hook, return the serviceId as array key and the corresponding deprecation method as the value.
     *
     * @internal
     *
     * @return array<string, string> The deprecated service name as array key, with the deprecation message as value.
     */
    public static function getDeprecatedServices(): array
    {
        return [];
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
