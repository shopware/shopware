<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Persister\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleContext;
use Shopware\Core\Framework\App\Lifecycle\Persister\Event\RuleConditionPersistEvent;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RuleConditionPersistEvent::class)]
class RuleConditionPersistEventTest extends TestCase
{
    public function testGetContextReturnsConstructorValue(): void
    {
        $app = new AppEntity();
        $app->setId('app-id');
        $app->setActive(true);

        $lifecycleContext = new AppLifecycleContext(
            manifest: $this->createMock(Manifest::class),
            app: $app,
            context: Context::createDefaultContext(),
            appFilesystem: new StaticFilesystem(),
            defaultLocale: 'en-GB',
            isInstall: true,
        );

        $event = new RuleConditionPersistEvent($lifecycleContext);

        static::assertSame($lifecycleContext, $event->getContext());
    }
}
