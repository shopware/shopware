<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\AgenticDiscovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AgenticDiscovery\AgenticDiscoveryDocumentType;
use Shopware\Core\Framework\AgenticDiscovery\Manifest\AgenticManifest;
use Shopware\Core\Framework\AgenticDiscovery\Manifest\AgenticManifestBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Storefront\Page\AgenticDiscovery\AgenticDiscoveryPageLoadedEvent;
use Shopware\Storefront\Page\AgenticDiscovery\AgenticDiscoveryPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AgenticDiscoveryPageLoader::class)]
class AgenticDiscoveryPageLoaderTest extends TestCase
{
    public function testReturnsNullWhenBuilderReturnsNull(): void
    {
        $builder = $this->createMock(AgenticManifestBuilder::class);
        $builder->method('buildForRequest')->willReturn(null);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $loader = new AgenticDiscoveryPageLoader($builder, $dispatcher);

        static::assertNull(
            $loader->load(AgenticDiscoveryDocumentType::AGENTS_MD, new Request(), Context::createDefaultContext())
        );
    }

    public function testBuildsPageAndDispatchesEventWhenManifestExists(): void
    {
        $manifest = new AgenticManifest(
            salesChannelId: 'sc-1',
            storeName: 'Acme',
            storeDescription: '',
            storeUrl: 'https://shop.acme.test',
            languageCode: 'en-GB',
            currencyCode: 'EUR',
            contactEmail: null,
            contactPhone: null,
            agentFlow: [],
            endpoints: [],
            catalogEndpoints: [],
            rules: [],
            browseLinks: [],
            sitemapEntries: [],
            customIntro: null,
            customSections: [],
            ucpAvailable: false,
            ucpProfileUrl: null,
        );

        $builder = $this->createMock(AgenticManifestBuilder::class);
        $builder->method('buildForRequest')->willReturn($manifest);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(AgenticDiscoveryPageLoadedEvent::class));

        $loader = new AgenticDiscoveryPageLoader($builder, $dispatcher);

        $page = $loader->load(AgenticDiscoveryDocumentType::LLMS_FULL_TXT, new Request(), Context::createDefaultContext());

        static::assertNotNull($page);
        static::assertSame(AgenticDiscoveryDocumentType::LLMS_FULL_TXT, $page->getType());
        static::assertSame($manifest, $page->getManifest());
    }
}
