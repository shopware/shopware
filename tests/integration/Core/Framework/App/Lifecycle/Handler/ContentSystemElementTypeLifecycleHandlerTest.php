<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Lifecycle\Handler;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Lifecycle\AppManager;
use Shopware\Core\Framework\App\Lifecycle\Handler\ContentSystemElementTypeLifecycleHandler;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\ContentSystemElementTypeRegistry;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Tests\Integration\Core\Framework\App\AppFixture;

/**
 * @internal
 */
#[Package('framework')]
class ContentSystemElementTypeLifecycleHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const FIXTURE = __DIR__ . '/_fixtures/element-type-lifecycle';

    private const ELEMENT_TYPE = 'element-type-lifecycle:Hero';

    #[TestDox('activating an app refreshes the cached registry and exposes its element types')]
    public function testActivationRefreshesTheCachedRegistry(): void
    {
        [$appManager, $handler, $registry, $fixture] = $this->services();

        $manifest = $fixture->loadManifest(self::FIXTURE . '/manifest.xml');
        $app = $fixture->createApp($manifest);
        $appManager->deactivate($app, Context::createDefaultContext());

        $context = $fixture->createInstallContext($app, $manifest, new Filesystem($manifest->getPath()));
        $handler->install($context);
        static::assertArrayNotHasKey(self::ELEMENT_TYPE, $registry->all());

        $appManager->activate($app, Context::createDefaultContext());

        static::assertArrayHasKey(self::ELEMENT_TYPE, $registry->all());
    }

    #[TestDox('deactivating an app refreshes the cached registry and hides its element types')]
    public function testDeactivationRefreshesTheCachedRegistry(): void
    {
        [$appManager, $handler, $registry, $fixture] = $this->services();

        $manifest = $fixture->loadManifest(self::FIXTURE . '/manifest.xml');
        $app = $fixture->createApp($manifest);
        $context = $fixture->createInstallContext($app, $manifest, new Filesystem($manifest->getPath()));
        $handler->install($context);
        static::assertArrayHasKey(self::ELEMENT_TYPE, $registry->all());

        $appManager->deactivate($app, Context::createDefaultContext());

        static::assertArrayNotHasKey(self::ELEMENT_TYPE, $registry->all());
    }

    /**
     * @return array{AppManager, ContentSystemElementTypeLifecycleHandler, AbstractContentSystemElementTypeRegistry, AppFixture}
     */
    private function services(): array
    {
        $appManager = static::getContainer()->get(AppManager::class);
        static::assertInstanceOf(AppManager::class, $appManager);

        $handler = static::getContainer()->get(ContentSystemElementTypeLifecycleHandler::class);
        static::assertInstanceOf(ContentSystemElementTypeLifecycleHandler::class, $handler);

        $registry = static::getContainer()->get(ContentSystemElementTypeRegistry::class);
        static::assertInstanceOf(AbstractContentSystemElementTypeRegistry::class, $registry);

        $fixture = static::getContainer()->get(AppFixture::class);
        static::assertInstanceOf(AppFixture::class, $fixture);

        return [$appManager, $handler, $registry, $fixture];
    }
}
