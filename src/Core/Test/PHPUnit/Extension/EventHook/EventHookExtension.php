<?php declare(strict_types=1);

namespace Shopware\Core\Test\PHPUnit\Extension\EventHook;

use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\PHPUnit\Extension\EventHook\Subscriber\ResetEventHooksSubscriber;

/**
 * Clears the hooks of the test-environment event dispatcher after each test.
 *
 * @internal
 */
#[Package('framework')]
class EventHookExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $facade->registerSubscribers(new ResetEventHooksSubscriber());
    }
}
