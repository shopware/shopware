<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade;

use Shopware\Core\Checkout\Cart\Hook\CartAware;
use Shopware\Core\Framework\Script\Exception\HookInjectionException;
use Shopware\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;

/**
 * @package checkout
 *
 * @internal
 */
class CartFacadeHookFactory extends HookServiceFactory
{
    private CartFacadeHelper $helper;

    public function __construct(CartFacadeHelper $helper)
    {
        $this->helper = $helper;
    }

    public function factory(Hook $hook, Script $script): CartFacade
    {
        if (!$hook instanceof CartAware) {
            throw new HookInjectionException($hook, self::class, CartAware::class);
        }

        return new CartFacade($this->helper, $hook->getCart(), $hook->getSalesChannelContext());
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
