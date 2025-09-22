<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ShopId;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * @internal
 */
#[Package('framework')]
#[AsEventListener]
class VerifyAppUrlListener
{
    public function __construct(private readonly AppUrlVerifier $appUrlVerifier)
    {
    }

    public function __invoke(ShopIdChangedEvent $event): void
    {
        $newShopId = $event->newShopId;

        $this->appUrlVerifier->verify($newShopId);
    }
}
