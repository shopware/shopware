<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution;

use Shopware\Core\Framework\Context;

/**
 * @internal (flag:FEATURE_NEXT_17441)
 */
abstract class Hook
{
    protected Context $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    /**
     * The services returned here must all implement the HookAwareService interface.
     * These are then available in the script under the variable `services`.
     */
    abstract public function getServiceIds(): array;

    /**
     * The name returned here serves as an accessor in the script for the service.
     * If e.g. `cart` is returned, the service is available under `services.cart`.
     */
    abstract public function getName(): string;

    public function getContext(): Context
    {
        return $this->context;
    }
}
