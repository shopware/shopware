<?php declare(strict_types=1);

namespace Shopware\Core\Test\PHPUnit\Extension\InAppPurchase\Subscriber;

use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\InAppPurchase;

/**
 * @internal
 */
#[Package('checkout')]
class TestFinishedSubscriber implements FinishedSubscriber
{
    public function notify(Finished $event): void
    {
        InAppPurchase::reset();
    }
}
