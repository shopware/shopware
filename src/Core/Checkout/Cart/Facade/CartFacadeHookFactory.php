<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade;

use Shopware\Core\Checkout\Cart\Hook\CartAware;
use Shopware\Core\Framework\Script\Exception\HookInjectionException;
use Shopware\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;

class CartFacadeHookFactory extends HookServiceFactory
{
    private Services $services;

    public function __construct(Services $services)
    {
        $this->services = $services;
    }

    public function factory(Hook $hook, Script $script): CartFacade
    {
        if (!$hook instanceof CartAware) {
            throw new HookInjectionException($hook, self::class, CartAware::class);
        }

        $this->services->setContext($hook->getSalesChannelContext());

        return new CartFacade($this->services, $hook->getCart());
    }

    /**
     * @param CartFacade $service
     */
    public function after(object $service, Hook $hook, Script $script): void
    {
        $service->calculate();
    }

    public function getName(): string
    {
        return 'cart';
    }
}
