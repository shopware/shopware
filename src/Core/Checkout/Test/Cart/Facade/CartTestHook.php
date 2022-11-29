<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Facade;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Hook\CartAware;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @package checkout
 *
 * @internal
 */
class CartTestHook extends Hook implements CartAware
{
    use SalesChannelContextAwareTrait;

    private string $name;

    private static array $serviceIds;

    private Cart $cart;

    /**
     * @param array<string> $serviceIds
     */
    public function __construct(string $name, Cart $cart, SalesChannelContext $context, array $data = [], array $serviceIds = [])
    {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
        $this->name = $name;
        self::$serviceIds = $serviceIds;
        $this->cart = $cart;

        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public static function getServiceIds(): array
    {
        return self::$serviceIds;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
