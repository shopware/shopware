<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Facade;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Hook\CartAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
class CartTestHook extends Hook implements CartAware
{
    use SalesChannelContextAwareTrait;

    private static array $serviceIds;

    /**
     * @param array<string> $serviceIds
     */
    public function __construct(
        private readonly string $name,
        private readonly Cart $cart,
        SalesChannelContext $context,
        array $data = [],
        array $serviceIds = []
    ) {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
        self::$serviceIds = $serviceIds;

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
