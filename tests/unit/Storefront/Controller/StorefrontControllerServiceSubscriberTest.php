<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Shopware\Core\Content\Media\MediaUrlPlaceholderHandlerInterface;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Routing\RequestTransformerInterface;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * @internal
 */
#[CoversClass(StorefrontController::class)]
class StorefrontControllerServiceSubscriberTest extends TestCase
{
    public function testImplementsServiceSubscriberInterface(): void
    {
        // Verify that StorefrontController properly implements the interface
        $interfaces = class_implements(StorefrontController::class);

        static::assertIsArray($interfaces);
        static::assertContains(ServiceSubscriberInterface::class, $interfaces);
    }

    public function testGetSubscribedServices(): void
    {
        $services = StorefrontController::getSubscribedServices();

        // Assert required framework services are subscribed
        static::assertArrayHasKey('twig', $services);
        static::assertArrayHasKey('event_dispatcher', $services);
        static::assertArrayHasKey('translator', $services);
        static::assertArrayHasKey('router', $services);
        static::assertArrayHasKey('request_stack', $services);

        // Assert Shopware specific services are subscribed
        static::assertArrayHasKey(SystemConfigService::class, $services);
        static::assertArrayHasKey(TemplateFinder::class, $services);
        static::assertArrayHasKey(SeoUrlPlaceholderHandlerInterface::class, $services);
        static::assertArrayHasKey(MediaUrlPlaceholderHandlerInterface::class, $services);
        static::assertArrayHasKey(ScriptExecutor::class, $services);
        static::assertArrayHasKey(RequestTransformerInterface::class, $services);
    }

    public function testServicesAreOptional(): void
    {
        $services = StorefrontController::getSubscribedServices();

        // All services should be optional (prefixed with ?)
        static::assertIsString($services['twig']);
        static::assertStringStartsWith('?', (string) $services['twig']);
        static::assertIsString($services['event_dispatcher']);
        static::assertStringStartsWith('?', (string) $services['event_dispatcher']);
        static::assertIsString($services['translator']);
        static::assertStringStartsWith('?', (string) $services['translator']);
        static::assertIsString($services['router']);
        static::assertStringStartsWith('?', (string) $services['router']);
        static::assertIsString($services['request_stack']);
        static::assertStringStartsWith('?', (string) $services['request_stack']);
        static::assertIsString($services[SystemConfigService::class]);
        static::assertStringStartsWith('?', (string) $services[SystemConfigService::class]);
        static::assertIsString($services[TemplateFinder::class]);
        static::assertStringStartsWith('?', (string) $services[TemplateFinder::class]);
        static::assertIsString($services[SeoUrlPlaceholderHandlerInterface::class]);
        static::assertStringStartsWith('?', (string) $services[SeoUrlPlaceholderHandlerInterface::class]);
        static::assertIsString($services[MediaUrlPlaceholderHandlerInterface::class]);
        static::assertStringStartsWith('?', (string) $services[MediaUrlPlaceholderHandlerInterface::class]);
        static::assertIsString($services[ScriptExecutor::class]);
        static::assertStringStartsWith('?', (string) $services[ScriptExecutor::class]);
        static::assertIsString($services[RequestTransformerInterface::class]);
        static::assertStringStartsWith('?', (string) $services[RequestTransformerInterface::class]);
    }

    public function testControllerWithServiceLocator(): void
    {
        $controller = new TestStorefrontController();

        // Create mock service locator
        $container = $this->createMock(ContainerInterface::class);

        // Configure the mock to return services when requested
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturnCallback(function (string $id) {
            $services = [
                'twig' => $this->createMock(Environment::class),
                'event_dispatcher' => $this->createMock(EventDispatcherInterface::class),
                'translator' => $this->createMock(TranslatorInterface::class),
                'router' => $this->createMock(RouterInterface::class),
                'request_stack' => $this->createMock(RequestStack::class),
                SystemConfigService::class => $this->createMock(SystemConfigService::class),
                TemplateFinder::class => $this->createMock(TemplateFinder::class),
                SeoUrlPlaceholderHandlerInterface::class => $this->createMock(SeoUrlPlaceholderHandlerInterface::class),
                MediaUrlPlaceholderHandlerInterface::class => $this->createMock(MediaUrlPlaceholderHandlerInterface::class),
                ScriptExecutor::class => $this->createMock(ScriptExecutor::class),
                RequestTransformerInterface::class => $this->createMock(RequestTransformerInterface::class),
            ];

            return $services[$id] ?? null;
        });

        $controller->setContainer($container);

        // Test that the controller can use the trans method
        $result = $controller->testTrans('test.key');
        static::assertIsString($result);
    }

    public function testInheritancePreservesSubscriptions(): void
    {
        $parentServices = StorefrontController::getSubscribedServices();
        $childServices = TestChildController::getSubscribedServices();

        // Child should have all parent services
        foreach ($parentServices as $key => $value) {
            static::assertArrayHasKey($key, $childServices);
        }

        // Child should have additional services
        static::assertArrayHasKey('custom.service', $childServices);
    }
}

/**
 * @internal
 * Test controller implementation for unit tests
 */
class TestStorefrontController extends StorefrontController
{
    public function testTrans(string $key): string
    {
        return $this->trans($key);
    }

    public function testAction(): Response
    {
        return new Response('test');
    }
}

/**
 * @internal
 * Test child controller with additional service subscriptions
 */
class TestChildController extends StorefrontController
{
    public static function getSubscribedServices(): array
    {
        return array_merge(parent::getSubscribedServices(), [
            'custom.service' => '?stdClass',
        ]);
    }
}
