<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;

/**
 * @internal
 */
#[Package('checkout')]
class PriceFactoryFactory extends HookServiceFactory
{
    public function __construct(private readonly ScriptPriceStubs $stubs)
    {
    }

    public function factory(Hook $hook, Script $script): PriceFactory
    {
        return new PriceFactory($this->stubs);
    }

    public function getName(): string
    {
        return 'price';
    }
}
