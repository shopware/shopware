<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\File\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\File\Discovery\SalesChannelFile;
use Shopware\Core\System\SalesChannel\File\Rendering\Extension\SalesChannelFileRenderParametersExtension;
use Shopware\Core\System\SalesChannel\File\Rendering\SalesChannelFileStoreApiMcpSubscriber;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal
 */
#[CoversClass(SalesChannelFileStoreApiMcpSubscriber::class)]
class SalesChannelFileStoreApiMcpSubscriberTest extends TestCase
{
    public function testStoreApiMcpUrlIsAddedForHeadlessAiCatalog(): void
    {
        $currentDomain = $this->createDomain('https://headless.example.com/en/');
        $fallbackDomain = $this->createDomain('https://fallback.example.com');
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('store-api.mcp.endpoint', [], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/store-api/_mcp');

        $subscriber = new SalesChannelFileStoreApiMcpSubscriber($urlGenerator);
        $extension = new SalesChannelFileRenderParametersExtension(
            $this->createSalesChannelFile('.well-known/ai-catalog.json'),
            $this->createSalesChannelContext($currentDomain->getId()),
            $this->createSalesChannel(
                Defaults::SALES_CHANNEL_TYPE_API,
                new SalesChannelDomainCollection([$fallbackDomain, $currentDomain])
            )
        );
        $extension->result = [];

        Feature::withFeatureEnabled('MCP_SERVER', static fn () => $subscriber->addStoreApiMcpContext($extension));

        static::assertSame([
            'salesChannelFileContext' => [
                'baseUrl' => 'https://headless.example.com/en',
                'publisher' => 'headless.example.com',
                'storeApiMcpServerUrl' => 'https://headless.example.com/en/store-api/_mcp',
            ],
        ], $extension->result);
    }

    public function testAiCatalogContextIsAddedWithoutStoreApiMcpUrl(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects($this->never())
            ->method('generate');

        $subscriber = new SalesChannelFileStoreApiMcpSubscriber($urlGenerator);
        $extension = new SalesChannelFileRenderParametersExtension(
            $this->createSalesChannelFile('.well-known/ai-catalog.json'),
            $this->createSalesChannelContext(null),
            $this->createSalesChannel(
                Defaults::SALES_CHANNEL_TYPE_API,
                new SalesChannelDomainCollection([$this->createDomain('https://headless.example.com')])
            )
        );
        $extension->result = [];

        Feature::withFeatureDisabled('MCP_SERVER', static fn () => $subscriber->addStoreApiMcpContext($extension));

        static::assertSame([
            'salesChannelFileContext' => [
                'baseUrl' => 'https://headless.example.com',
                'publisher' => 'headless.example.com',
            ],
        ], $extension->result);
    }

    public function testSalesChannelFileContextIsOnlyAddedForAiCatalog(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects($this->never())
            ->method('generate');

        $extension = new SalesChannelFileRenderParametersExtension(
            $this->createSalesChannelFile('llms.txt'),
            $this->createSalesChannelContext(null),
            $this->createSalesChannel(
                Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
                new SalesChannelDomainCollection([$this->createDomain('https://storefront.example.com')])
            )
        );
        $extension->result = [];

        (new SalesChannelFileStoreApiMcpSubscriber($urlGenerator))->addStoreApiMcpContext($extension);

        static::assertSame([], $extension->result);
    }

    private function createSalesChannelContext(?string $domainId): SalesChannelContext
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getDomainId')->willReturn($domainId);

        return $context;
    }

    private function createSalesChannel(string $typeId, SalesChannelDomainCollection $domains): SalesChannelEntity
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setTypeId($typeId);
        $salesChannel->setDomains($domains);

        return $salesChannel;
    }

    private function createDomain(string $url): SalesChannelDomainEntity
    {
        $domain = new SalesChannelDomainEntity();
        $domain->setId(Uuid::randomHex());
        $domain->setUrl($url);

        return $domain;
    }

    private function createSalesChannelFile(string $fileName): SalesChannelFile
    {
        return new SalesChannelFile(
            'agentic',
            $fileName,
            'files/agentic/' . $fileName . '.twig',
            'application/json; charset=utf-8',
            'files/agentic/' . $fileName . '.twig',
            []
        );
    }
}
