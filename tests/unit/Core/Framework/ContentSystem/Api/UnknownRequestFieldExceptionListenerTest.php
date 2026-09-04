<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\UnknownRequestFieldExceptionListener;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UnknownRequestFieldExceptionListener::class)]
class UnknownRequestFieldExceptionListenerTest extends TestCase
{
    #[TestDox('remaps an ExtraAttributesException on a content-system route to a 400 unknownRequestField naming the fields')]
    public function testRemapsExtraAttributesOnContentSystemRoute(): void
    {
        $event = $this->event('api.action.content_system.layout.diagnose', new ExtraAttributesException(['entityType', 'section']));

        (new UnknownRequestFieldExceptionListener())->onKernelException($event);

        $throwable = $event->getThrowable();
        static::assertInstanceOf(ContentSystemException::class, $throwable);
        static::assertSame(ContentSystemException::UNKNOWN_REQUEST_FIELD, $throwable->getErrorCode());
        static::assertStringContainsString('entityType', $throwable->getMessage());
        static::assertStringContainsString('section', $throwable->getMessage());
    }

    #[TestDox('walks the cause chain and remaps an ExtraAttributesException wrapped as a previous exception')]
    public function testRemapsWrappedExtraAttributesException(): void
    {
        $wrapped = new \RuntimeException('mapping failed', 0, new ExtraAttributesException(['entityType']));
        $event = $this->event('api.action.content_system.layout.persisted_insert_element', $wrapped);

        (new UnknownRequestFieldExceptionListener())->onKernelException($event);

        $throwable = $event->getThrowable();
        static::assertInstanceOf(ContentSystemException::class, $throwable);
        static::assertSame(ContentSystemException::UNKNOWN_REQUEST_FIELD, $throwable->getErrorCode());
    }

    #[TestDox('leaves the throwable untouched on a route outside the content-system prefix')]
    public function testIgnoresNonContentSystemRoute(): void
    {
        $original = new ExtraAttributesException(['entityType']);
        $event = $this->event('api.some.other.route', $original);

        (new UnknownRequestFieldExceptionListener())->onKernelException($event);

        static::assertSame($original, $event->getThrowable());
    }

    #[TestDox('leaves a non-ExtraAttributes throwable untouched on a content-system route')]
    public function testIgnoresOtherExceptionsOnContentSystemRoute(): void
    {
        $original = new \RuntimeException('boom');
        $event = $this->event('api.action.content_system.layout.diagnose', $original);

        (new UnknownRequestFieldExceptionListener())->onKernelException($event);

        static::assertSame($original, $event->getThrowable());
    }

    private function event(string $route, \Throwable $throwable): ExceptionEvent
    {
        $request = new Request();
        $request->attributes->set('_route', $route);

        return new ExceptionEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $throwable,
        );
    }
}
