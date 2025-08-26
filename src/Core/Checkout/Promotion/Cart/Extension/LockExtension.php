<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Extension;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Lock\LockInterface;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class LockExtension extends Struct
{
    /**
     * this is the key that should be
     * used for the cart extension
     */
    final public const KEY = 'promotion-cart-locks';

    /**
     * @param array<string, LockInterface> $locks
     */
    public function __construct(
        public readonly array $locks
    ) {
    }
}
