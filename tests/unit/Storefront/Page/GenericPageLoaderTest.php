<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Pagelet\Footer\FooterPageletLoaderInterface;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(GenericPageLoader::class)]
class GenericPageLoaderTest extends TestCase
{
    public function testLoad(): void
    {
        $headerPageletLoader = $this->createMock(HeaderPageletLoaderInterface::class);
        $headerPageletLoader->expects(static::never())->method('load');

        $footerPageletLoader = $this->createMock(FooterPageletLoaderInterface::class);
        $footerPageletLoader->expects(static::never())->method('load');

        $paymentMethodRoute = $this->createMock(AbstractPaymentMethodRoute::class);
        $paymentMethodRoute->expects(static::never())->method('load');

        $shippingMethodRoute = $this->createMock(AbstractShippingMethodRoute::class);
        $shippingMethodRoute->expects(static::never())->method('load');

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->method('getString')->willReturn('Shopware');

        $loader = new GenericPageLoader(
            $headerPageletLoader,
            $footerPageletLoader,
            $systemConfigService,
            $paymentMethodRoute,
            $shippingMethodRoute,
            $this->createMock(EventDispatcherInterface::class)
        );

        $request = new Request(attributes: [SalesChannelRequest::ATTRIBUTE_DOMAIN_LOCALE => 'en-GB']);

        $metaInformation = $loader->load($request, Generator::generateSalesChannelContext())->getMetaInformation();
        static::assertNotNull($metaInformation);
        static::assertSame('Shopware', $metaInformation->getMetaTitle());
        static::assertSame('en-GB', $metaInformation->getXmlLang());
    }
}
