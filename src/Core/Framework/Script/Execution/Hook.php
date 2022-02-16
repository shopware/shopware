<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution;

use Shopware\Core\Framework\Context;

/**
 * @deprecated tag:v6.5.0 will be internal
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
     */
    abstract public static function getServiceIds(): array;

    abstract public function getName(): string;

    public function getContext(): Context
    {
        return $this->context;
    }
}
