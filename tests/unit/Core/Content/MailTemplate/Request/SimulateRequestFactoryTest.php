<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate\Request;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateException;
use Shopware\Core\Content\MailTemplate\Request\SimulateRequestFactory;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\SalesChannelProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(SimulateRequestFactory::class)]
class SimulateRequestFactoryTest extends TestCase
{
    private SalesChannelProvider&MockObject $salesChannelProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salesChannelProvider = $this->createMock(SalesChannelProvider::class);
    }

    public function testMakeBuildsRequest(): void
    {
        $context = Context::createDefaultContext();
        $salesChannel = new SalesChannelEntity();
        $request = new RequestDataBag([
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

        $result = (new SimulateRequestFactory($this->salesChannelProvider))->make($request, $context);

        static::assertSame(['contentHtml' => 'Hello {{ email }}'], $result->templateParts);
        static::assertSame('checkout.customer.before.login', $result->eventName);
        static::assertSame($salesChannel, $result->salesChannel);
        static::assertFalse($result->strictRendering);
    }

    public function testMakeAcceptsArrayMailTemplateContent(): void
    {
        $context = Context::createDefaultContext();
        $request = new RequestDataBag([
            'templateParts' => ['contentHtml' => 'Hello {{ email }}'],
            'eventName' => 'checkout.customer.before.login',
        ]);

        $result = (new SimulateRequestFactory($this->salesChannelProvider))->make($request, $context);

        static::assertSame(['contentHtml' => 'Hello {{ email }}'], $result->templateParts);
        static::assertSame('checkout.customer.before.login', $result->eventName);
        static::assertNull($result->salesChannel);
        static::assertTrue($result->strictRendering);
    }

    public function testMakeThrowsForInvalidMailTemplateContent(): void
    {
        $context = Context::createDefaultContext();
        $request = new RequestDataBag([
            'templateParts' => 'invalid',
            'eventName' => 'checkout.customer.before.login',
        ]);

        $this->expectExceptionObject(
            MailTemplateException::invalidRequestParameterType('templateParts', 'array|object', 'string')
        );

        (new SimulateRequestFactory($this->salesChannelProvider))->make($request, $context);
    }

    public function testMakeThrowsForInvalidStrict(): void
    {
        $context = Context::createDefaultContext();
        $request = new RequestDataBag([
            'templateParts' => ['contentHtml' => 'Hello {{ email }}'],
            'eventName' => 'checkout.customer.before.login',
            'strictRendering' => 'invalid',
        ]);

        $this->expectExceptionObject(
            MailTemplateException::invalidRequestParameterType('strictRendering', 'bool', 'string')
        );

        (new SimulateRequestFactory($this->salesChannelProvider))->make($request, $context);
    }

    public function testMakeThrowsForInvalidSalesChannelIdType(): void
    {
        $context = Context::createDefaultContext();
        $request = new RequestDataBag([
            'templateParts' => ['contentHtml' => 'Hello {{ email }}'],
            'eventName' => 'checkout.customer.before.login',
            'salesChannelId' => 1,
        ]);

        $this->expectExceptionObject(
            MailTemplateException::invalidRequestParameterType('salesChannelId', 'string', 'int')
        );

        (new SimulateRequestFactory($this->salesChannelProvider))->make($request, $context);
    }

    public function testMakeThrowsForUnknownSalesChannelId(): void
    {
        $context = Context::createDefaultContext();
        $request = new RequestDataBag([
            'templateParts' => ['contentHtml' => 'Hello {{ email }}'],
            'eventName' => 'checkout.customer.before.login',
            'salesChannelId' => 'sales-channel-id',
        ]);

        $this->salesChannelProvider->expects($this->once())
            ->method('getData')
            ->with('sales-channel-id', $context)
            ->willReturn(null);

        $this->expectExceptionObject(MailTemplateException::invalidSalesChannelId('sales-channel-id'));

        (new SimulateRequestFactory($this->salesChannelProvider))->make($request, $context);
    }
}
