<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate\Request;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateException;
use Shopware\Core\Content\MailTemplate\Request\Resolver\SimulateRequestResolver;
use Shopware\Core\Content\MailTemplate\Request\SimulateRequest;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\SalesChannelProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(SimulateRequestResolver::class)]
class SimulateRequestResolverTest extends TestCase
{
    private SalesChannelProvider&MockObject $salesChannelProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salesChannelProvider = $this->createMock(SalesChannelProvider::class);
    }

    public function testResolveBuildsRequest(): void
    {
        $context = Context::createDefaultContext();
        $salesChannel = new SalesChannelEntity();
        $request = $this->createRequest($context, [
            'templateParts' => new DataBag([
                'contentHtml' => 'Hello {{ email }}',
            ]),
            'eventName' => 'checkout.customer.before.login',
            'strictRendering' => false,
            'salesChannelId' => 'sales-channel-id',
        ]);

        $this->salesChannelProvider->expects($this->once())
            ->method('getData')
            ->with('sales-channel-id', $context)
            ->willReturn($salesChannel);

        $result = $this->resolveRequest($request);

        static::assertSame(['contentHtml' => 'Hello {{ email }}'], $result->templateParts);
        static::assertSame('checkout.customer.before.login', $result->eventName);
        static::assertSame($salesChannel, $result->salesChannel);
        static::assertFalse($result->strictRendering);
    }

    public function testResolveAcceptsArrayMailTemplateContent(): void
    {
        $context = Context::createDefaultContext();
        $request = $this->createRequest($context, [
            'templateParts' => ['contentHtml' => 'Hello {{ email }}'],
            'eventName' => 'checkout.customer.before.login',
        ]);

        $result = $this->resolveRequest($request);

        static::assertSame(['contentHtml' => 'Hello {{ email }}'], $result->templateParts);
        static::assertSame('checkout.customer.before.login', $result->eventName);
        static::assertNull($result->salesChannel);
        static::assertTrue($result->strictRendering);
    }

    public function testResolveThrowsForInvalidMailTemplateContent(): void
    {
        $context = Context::createDefaultContext();
        $request = $this->createRequest($context, [
            'templateParts' => 'invalid',
            'eventName' => 'checkout.customer.before.login',
        ]);

        $this->expectExceptionObject(
            MailTemplateException::invalidRequestParameterType('templateParts', 'array|object', 'string')
        );

        $this->resolveRequest($request);
    }

    public function testResolveThrowsForInvalidStrict(): void
    {
        $context = Context::createDefaultContext();
        $request = $this->createRequest($context, [
            'templateParts' => ['contentHtml' => 'Hello {{ email }}'],
            'eventName' => 'checkout.customer.before.login',
            'strictRendering' => 'invalid',
        ]);

        $this->expectExceptionObject(
            MailTemplateException::invalidRequestParameterType('strictRendering', 'bool', 'string')
        );

        $this->resolveRequest($request);
    }

    public function testResolveThrowsForInvalidSalesChannelIdType(): void
    {
        $context = Context::createDefaultContext();
        $request = $this->createRequest($context, [
            'templateParts' => ['contentHtml' => 'Hello {{ email }}'],
            'eventName' => 'checkout.customer.before.login',
            'salesChannelId' => 1,
        ]);

        $this->expectExceptionObject(
            MailTemplateException::invalidRequestParameterType('salesChannelId', 'string', 'int')
        );

        $this->resolveRequest($request);
    }

    public function testResolveThrowsForUnknownSalesChannelId(): void
    {
        $context = Context::createDefaultContext();
        $request = $this->createRequest($context, [
            'templateParts' => ['contentHtml' => 'Hello {{ email }}'],
            'eventName' => 'checkout.customer.before.login',
            'salesChannelId' => 'sales-channel-id',
        ]);

        $this->salesChannelProvider->expects($this->once())
            ->method('getData')
            ->with('sales-channel-id', $context)
            ->willReturn(null);

        $this->expectExceptionObject(MailTemplateException::invalidSalesChannelId('sales-channel-id'));

        $this->resolveRequest($request);
    }

    private function resolveRequest(Request $request): SimulateRequest
    {
        $resolver = new SimulateRequestResolver($this->salesChannelProvider);

        return iterator_to_array(
            $resolver->resolve($request, new ArgumentMetadata('simulateRequest', SimulateRequest::class, false, false, null))
        )[0];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createRequest(Context $context, array $payload): Request
    {
        $request = new Request([], $payload);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);

        return $request;
    }
}
