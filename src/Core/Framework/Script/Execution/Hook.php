<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution;

use Shopware\Core\Framework\Context;

/**
 * @internal
 */
abstract class Hook
{
    protected Context $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    /**
     * The services returned here must all extend the abstract HookServiceFactory.
     * These are then available in the script under the variable `services`.
     *
     * @return list<class-string<object>>
     */
    abstract public static function getServiceIds(): array;

    abstract public function getName(): string;

    /**
     * If a service will be removed from a hook, return the serviceId as array key and the corresponding deprecation method as the value.
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
