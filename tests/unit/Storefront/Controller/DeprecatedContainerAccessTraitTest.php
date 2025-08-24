<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Shopware\Storefront\Controller\DeprecatedContainerAccessTrait;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @phpstan-ignore classConstant.deprecatedTrait
 */
#[CoversClass(DeprecatedContainerAccessTrait::class)]
class DeprecatedContainerAccessTraitTest extends TestCase
{
    private TestControllerWithDeprecatedTrait $controller;

    /**
     * @var MockObject&ContainerInterface
     */
    private MockObject $container;

    protected function setUp(): void
    {
        $this->controller = new TestControllerWithDeprecatedTrait();
        $this->container = $this->createMock(ContainerInterface::class);
        $this->controller->setContainer($this->container);
    }

    public function testGetServiceDeprecatedReturnsService(): void
    {
        $mockService = new \stdClass();
        $mockService->test = 'value';

        $this->container
            ->expects($this->once())
            ->method('has')
            ->with('test.service')
            ->willReturn(true);

        $this->container
            ->expects($this->once())
            ->method('get')
            ->with('test.service')
            ->willReturn($mockService);

        // Capture deprecation warning
        $deprecations = [];
        set_error_handler(function ($errno, $errstr) use (&$deprecations) {
            if ($errno === \E_USER_DEPRECATED) {
                $deprecations[] = $errstr;
            }

            return true;
        }, \E_USER_DEPRECATED);

        $result = $this->controller->getTestService('test.service');

        restore_error_handler();

        static::assertSame($mockService, $result);
        static::assertEquals('value', $result->test);
        static::assertCount(1, $deprecations);
        static::assertStringContainsString('Direct container access via $this->container->get("test.service")', $deprecations[0]);
    }

    public function testGetServiceDeprecatedReturnsNullWhenServiceNotExists(): void
    {
        $this->container
            ->expects($this->once())
            ->method('has')
            ->with('non.existent.service')
            ->willReturn(false);

        $this->container
            ->expects($this->never())
            ->method('get');

        // Capture deprecation warning
        $deprecations = [];
        set_error_handler(function ($errno, $errstr) use (&$deprecations) {
            if ($errno === \E_USER_DEPRECATED) {
                $deprecations[] = $errstr;
            }

            return true;
        }, \E_USER_DEPRECATED);

        $result = $this->controller->getTestService('non.existent.service');

        restore_error_handler();

        static::assertNull($result);
        static::assertCount(1, $deprecations);
        static::assertStringContainsString('Direct container access via $this->container->get("non.existent.service")', $deprecations[0]);
    }

    public function testHasServiceDeprecatedReturnsTrueWhenServiceExists(): void
    {
        $this->container
            ->expects($this->once())
            ->method('has')
            ->with('existing.service')
            ->willReturn(true);

        // Capture deprecation warning
        $deprecations = [];
        set_error_handler(function ($errno, $errstr) use (&$deprecations) {
            if ($errno === \E_USER_DEPRECATED) {
                $deprecations[] = $errstr;
            }

            return true;
        }, \E_USER_DEPRECATED);

        $result = $this->controller->hasTestService('existing.service');

        restore_error_handler();

        static::assertTrue($result);
        static::assertCount(1, $deprecations);
        static::assertStringContainsString('Checking service availability via hasServiceDeprecated("existing.service")', $deprecations[0]);
    }

    public function testHasServiceDeprecatedReturnsFalseWhenServiceNotExists(): void
    {
        $this->container
            ->expects($this->once())
            ->method('has')
            ->with('non.existent.service')
            ->willReturn(false);

        // Capture deprecation warning
        $deprecations = [];
        set_error_handler(function ($errno, $errstr) use (&$deprecations) {
            if ($errno === \E_USER_DEPRECATED) {
                $deprecations[] = $errstr;
            }

            return true;
        }, \E_USER_DEPRECATED);

        $result = $this->controller->hasTestService('non.existent.service');

        restore_error_handler();

        static::assertFalse($result);
        static::assertCount(1, $deprecations);
        static::assertStringContainsString('Checking service availability via hasServiceDeprecated("non.existent.service")', $deprecations[0]);
    }

    public function testDeprecationMessageIncludesClassName(): void
    {
        $this->container
            ->expects($this->once())
            ->method('has')
            ->with('test.service')
            ->willReturn(true);

        $this->container
            ->expects($this->once())
            ->method('get')
            ->with('test.service')
            ->willReturn(new \stdClass());

        // Capture deprecation warning
        $deprecations = [];
        set_error_handler(function ($errno, $errstr) use (&$deprecations) {
            if ($errno === \E_USER_DEPRECATED) {
                $deprecations[] = $errstr;
            }

            return true;
        }, \E_USER_DEPRECATED);

        $this->controller->getTestService('test.service');

        restore_error_handler();

        static::assertCount(1, $deprecations);
        static::assertStringContainsString(TestControllerWithDeprecatedTrait::class, $deprecations[0]);
        static::assertStringContainsString('Declare the service in getSubscribedServices() method instead', $deprecations[0]);
    }

    public function testDeprecationVersionNumbers(): void
    {
        $this->container
            ->expects($this->once())
            ->method('has')
            ->with('test.service')
            ->willReturn(true);

        $this->container
            ->expects($this->once())
            ->method('get')
            ->with('test.service')
            ->willReturn(new \stdClass());

        // Use error handler to capture deprecation details
        $deprecations = [];
        set_error_handler(function ($errno, $errstr) use (&$deprecations) {
            if ($errno === \E_USER_DEPRECATED) {
                $deprecations[] = $errstr;
            }

            return true;
        });

        $this->controller->getTestService('test.service');

        restore_error_handler();

        static::assertCount(1, $deprecations);
        static::assertStringContainsString('6.8.0', $deprecations[0]);
        static::assertStringContainsString('shopware/storefront', $deprecations[0]);
    }
}

/**
 * @internal
 * Test controller that uses the deprecated trait for testing purposes
 */
class TestControllerWithDeprecatedTrait extends StorefrontController
{
    use DeprecatedContainerAccessTrait;

    /**
     * Public wrapper for testing protected method
     */
    public function getTestService(string $id): ?object
    {
        return $this->getServiceDeprecated($id);
    }

    /**
     * Public wrapper for testing protected method
     */
    public function hasTestService(string $id): bool
    {
        return $this->hasServiceDeprecated($id);
    }

    public function testAction(): Response
    {
        return new Response('test');
    }
}
