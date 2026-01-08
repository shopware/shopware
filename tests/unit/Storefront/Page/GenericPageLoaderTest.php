<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\SalesChannelRequestAttribute;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Page\GenericPageLoader;
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
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->method('getString')->willReturn('Shopware');

        $loader = new GenericPageLoader(
            $systemConfigService,
            $this->createMock(EventDispatcherInterface::class)
        );

        $request = new Request(attributes: [SalesChannelRequestAttribute::DOMAIN_LOCALE->value => 'en-GB']);

        $metaInformation = $loader->load($request, Generator::generateSalesChannelContext())->getMetaInformation();
        static::assertNotNull($metaInformation);
        static::assertSame('Shopware', $metaInformation->getMetaTitle());
        static::assertSame('en-GB', $metaInformation->getXmlLang());
    }
}
