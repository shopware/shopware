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

        $this->expectUserDeprecationMessageMatches('/Since shopware\/storefront 6\.8\.0: Direct container access via \$this->container->get\("test\.service"\)/');

        $result = $this->controller->getTestService('test.service');

        static::assertSame($mockService, $result);
        static::assertEquals('value', $result->test);
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

        $this->expectUserDeprecationMessageMatches('/Since shopware\/storefront 6\.8\.0: Direct container access via \$this->container->get\("non\.existent\.service"\)/');

        $result = $this->controller->getTestService('non.existent.service');

        static::assertNull($result);
    }

    public function testHasServiceDeprecatedReturnsTrueWhenServiceExists(): void
    {
        $this->container
            ->expects($this->once())
            ->method('has')
            ->with('existing.service')
            ->willReturn(true);

        $this->expectUserDeprecationMessageMatches('/Since shopware\/storefront 6\.8\.0: Checking service availability via hasServiceDeprecated\("existing\.service"\)/');

        $result = $this->controller->hasTestService('existing.service');

        static::assertTrue($result);
    }

    public function testHasServiceDeprecatedReturnsFalseWhenServiceNotExists(): void
    {
        $this->container
            ->expects($this->once())
            ->method('has')
            ->with('non.existent.service')
            ->willReturn(false);

        $this->expectUserDeprecationMessageMatches('/Since shopware\/storefront 6\.8\.0: Checking service availability via hasServiceDeprecated\("non\.existent\.service"\)/');

        $result = $this->controller->hasTestService('non.existent.service');

        static::assertFalse($result);
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

        $this->expectUserDeprecationMessageMatches('/Since shopware\/storefront 6\.8\.0:.*' . preg_quote(TestControllerWithDeprecatedTrait::class, '/') . '.*Declare the service in getSubscribedServices\(\) method instead/');

        $this->controller->getTestService('test.service');
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

        $this->expectUserDeprecationMessageMatches('/Since shopware\/storefront 6\.8\.0:/');

        $this->controller->getTestService('test.service');
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
