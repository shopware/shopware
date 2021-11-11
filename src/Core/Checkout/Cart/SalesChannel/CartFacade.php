<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Framework\Script\Exception\HookInjectionException;
use Shopware\Core\Framework\Script\Execution\Awareness\HookAwareService;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CartFacade extends HookAwareService
{
    private CartService $service;

    private LineItemFactoryRegistry $factory;

    private SalesChannelContext $context;

    public function __construct(CartService $service, LineItemFactoryRegistry $factory)
    {
        $this->service = $service;
        $this->factory = $factory;
    }

    public function inject(Hook $hook): void
    {
        if (!$hook instanceof SalesChannelContextAware) {
            throw new HookInjectionException($hook, self::class, SalesChannelContextAware::class);
        }

        $this->context = $hook->getSalesChannelContext();
    }

    public function getName(): string
    {
        return 'cart';
    }

    public function cart(): Cart
    {
        // don't expose whole cart object
        return clone $this->getCart();
    }

    public function addProduct(string $productId, int $quantity = 1): ?LineItem
    {
        $data = [
            'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
            'id' => $productId,
            'referencedId' => $productId,
            'quantity' => $quantity,
        ];

        $product = $this->factory->create($data, $this->context);

        $this->service->add($this->getCart(), [$product], $this->context);

        return $this->cart()->get($product->getId());
    }

    public function remove(string $key): bool
    {
        if (!$this->getCart()->has($key)) {
            return false;
        }
        $this->getCart()->remove($key);

        return true;
    }

    private function getCart(): Cart
    {
        return $this->service->getCart($this->context->getToken(), $this->context);
    }
}
