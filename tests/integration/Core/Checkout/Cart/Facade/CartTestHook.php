<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart\Facade;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Hook\CartAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('checkout')]
class CartTestHook extends Hook implements CartAware
{
    use SalesChannelContextAwareTrait;

    public IdsCollection $ids;

    /**
     * @var list<class-string<object>>
     */
    private static array $serviceIds;

    /**
     * @param list<class-string<object>> $serviceIds
     * @param array<string, mixed> $data
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
            $this->$key = $value; /* @phpstan-ignore-line */
        }
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    /**
     * @return list<class-string<object>>
     */
    public static function getServiceIds(): array
    {
        return self::$serviceIds;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
