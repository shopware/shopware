<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextRequestRestorer;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(SalesChannelContextRequestRestorer::class)]
class SalesChannelContextRequestRestorerTest extends TestCase
{
    public function testItReturnsExistingContextWithoutLoadingItAgain(): void
    {
        $existingContext = $this->createMock(SalesChannelContext::class);
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $existingContext);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, TestDefaults::SALES_CHANNEL);

        $contextService = $this->createMock(SalesChannelContextServiceInterface::class);
        $contextService
            ->expects($this->never())
            ->method('get');

        $restorer = new SalesChannelContextRequestRestorer($contextService);

        static::assertSame($existingContext, $restorer->restore($request));
    }

    public function testItReturnsNullWithoutSalesChannelId(): void
    {
        $contextService = $this->createMock(SalesChannelContextServiceInterface::class);
        $contextService
            ->expects($this->never())
            ->method('get');

        $restorer = new SalesChannelContextRequestRestorer($contextService);

        static::assertNull($restorer->restore(new Request()));
    }

    public function testItLoadsAndStoresContextFromSalesChannelRequestAttributes(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, TestDefaults::SALES_CHANNEL);
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID, Defaults::CURRENCY);
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_ID, 'domain-id');
        $request->headers->set(PlatformRequest::HEADER_LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM);

        $contextService = $this->createMock(SalesChannelContextServiceInterface::class);
        $contextService
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(static function (SalesChannelContextServiceParameters $parameters) use ($context): SalesChannelContext {
                static::assertSame(TestDefaults::SALES_CHANNEL, $parameters->getSalesChannelId());
                static::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $parameters->getToken());
                static::assertSame(Defaults::LANGUAGE_SYSTEM, $parameters->getLanguageId());
                static::assertSame(Defaults::CURRENCY, $parameters->getCurrencyId());
                static::assertSame('domain-id', $parameters->getDomainId());

                return $context;
            });

        $restorer = new SalesChannelContextRequestRestorer($contextService);

        static::assertSame($context, $restorer->restore($request));
        static::assertSame($context, $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT));
    }
}
