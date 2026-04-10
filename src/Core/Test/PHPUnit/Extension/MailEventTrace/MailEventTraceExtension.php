<?php declare(strict_types=1);

namespace Shopware\Core\Test\PHPUnit\Extension\MailEventTrace;

use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\PHPUnit\Extension\MailEventTrace\Subscriber\BeforeTestMethodCalledSubscriber;
use Shopware\Core\Test\PHPUnit\Extension\MailEventTrace\Subscriber\TestFinishedSubscriber;

/**
 * Diagnostic extension: captures a stack trace for every mail.sent dispatch and prints
 * them after each test. Enable in phpunit.xml.dist to locate unexpected mail dispatches.
 *
 * @internal
 */
#[Package('framework')]
class MailEventTraceExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $tracer = new MailEventTracer();

        $facade->registerSubscribers(
            new BeforeTestMethodCalledSubscriber($tracer),
            new TestFinishedSubscriber($tracer),
        );
    }
}
