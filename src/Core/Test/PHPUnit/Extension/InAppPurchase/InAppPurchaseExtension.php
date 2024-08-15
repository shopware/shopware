<?php declare(strict_types=1);

namespace Shopware\Core\Test\PHPUnit\Extension\InAppPurchase;

use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\PHPUnit\Extension\InAppPurchase\Subscriber\TestFinishedSubscriber;

/**
 * @internal
 */
#[Package('checkout')]
class InAppPurchaseExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $facade->registerSubscriber(new TestFinishedSubscriber());
    }
}
