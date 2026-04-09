<?php declare(strict_types=1);

namespace Shopware\Core\Test\PHPUnit\Extension\RequestStackDirty;

use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\PHPUnit\Extension\RequestStackDirty\Subscriber\TestFinishedSubscriber;
use Shopware\Core\Test\PHPUnit\Extension\RequestStackDirty\Subscriber\TestPreparationStartedSubscriber;

/**
 * @internal
 */
#[Package('framework')]
class RequestStackDirtyExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $stackDepth = new StackDepth();

        $facade->registerSubscribers(
            new TestPreparationStartedSubscriber($stackDepth),
            new TestFinishedSubscriber($stackDepth)
        );
    }
}
