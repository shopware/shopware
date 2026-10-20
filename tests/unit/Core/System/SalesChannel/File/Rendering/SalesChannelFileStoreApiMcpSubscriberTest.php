<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\File\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
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
#[Package('framework')]
#[CoversClass(SalesChannelFileStoreApiMcpSubscriber::class)]
class SalesChannelFileStoreApiMcpSubscriberTest extends TestCase
{
    private const APP_URL = 'https://shopware.example.com/';

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

        $subscriber = new SalesChannelFileStoreApiMcpSubscriber($urlGenerator, self::APP_URL);
        $extension = new SalesChannelFileRenderParametersExtension(
            $this->createSalesChannelFile('.well-known/ai-catalog.json'),
            $this->createSalesChannelContext($currentDomain->getId()),
            $this->createSalesChannel(
                Defaults::SALES_CHANNEL_TYPE_API,
                new SalesChannelDomainCollection([$fallbackDomain, $currentDomain])
            )
        );
        $extension->result = [];

        $subscriber->addStoreApiMcpContext($extension);

        static::assertSame([
            'salesChannelFileContext' => [
                'appUrl' => 'https://shopware.example.com',
                'baseUrl' => 'https://headless.example.com/en',
                'publisher' => 'headless.example.com',
                'storeApiMcpServerUrl' => 'https://shopware.example.com/store-api/_mcp',
            ],
        ], $extension->result);
    }

    public function testDomainMatchingTheContextLanguageIsUsedWithoutDomainInTheRequest(): void
    {
        $englishDomain = $this->createDomain('https://en.headless.example.com', Uuid::randomHex());
        $germanDomain = $this->createDomain('https://de.headless.example.com', Uuid::randomHex());

        $extension = new SalesChannelFileRenderParametersExtension(
            $this->createSalesChannelFile('.well-known/ai-catalog.json'),
            $this->createSalesChannelContext(null, $englishDomain->getLanguageId()),
            $this->createSalesChannel(
                Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
                new SalesChannelDomainCollection([$germanDomain, $englishDomain])
            )
        );
        $extension->result = [];

        (new SalesChannelFileStoreApiMcpSubscriber(static::createStub(UrlGeneratorInterface::class), self::APP_URL))->addStoreApiMcpContext($extension);

        static::assertSame([
            'salesChannelFileContext' => [
                'appUrl' => 'https://shopware.example.com',
                'baseUrl' => 'https://en.headless.example.com',
                'publisher' => 'en.headless.example.com',
            ],
        ], $extension->result);
    }

    public function testFirstDomainIsUsedWithoutDomainMatchingTheContextLanguage(): void
    {
        $firstDomain = $this->createDomain('https://first.example.com', Uuid::randomHex());
        $secondDomain = $this->createDomain('https://second.example.com', Uuid::randomHex());

        $extension = new SalesChannelFileRenderParametersExtension(
            $this->createSalesChannelFile('llms.txt'),
            $this->createSalesChannelContext(null, Uuid::randomHex()),
            $this->createSalesChannel(
                Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
                new SalesChannelDomainCollection([$firstDomain, $secondDomain])
            )
        );
        $extension->result = [];

        (new SalesChannelFileStoreApiMcpSubscriber(static::createStub(UrlGeneratorInterface::class), self::APP_URL))->addStoreApiMcpContext($extension);

        static::assertSame([
            'salesChannelFileContext' => [
                'appUrl' => 'https://shopware.example.com',
                'baseUrl' => 'https://first.example.com',
                'publisher' => 'first.example.com',
            ],
        ], $extension->result);
    }

    public function testStoreApiMcpUrlIsAddedForHeadlessSalesChannelWithoutDomains(): void
    {
        $urlGenerator = static::createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/store-api/_mcp');

        $extension = new SalesChannelFileRenderParametersExtension(
            $this->createSalesChannelFile('.well-known/ai-catalog.json'),
            $this->createSalesChannelContext(null),
            $this->createSalesChannel(Defaults::SALES_CHANNEL_TYPE_API, new SalesChannelDomainCollection())
        );
        $extension->result = [];

        (new SalesChannelFileStoreApiMcpSubscriber($urlGenerator, self::APP_URL))->addStoreApiMcpContext($extension);

        static::assertSame([
            'salesChannelFileContext' => [
                'appUrl' => 'https://shopware.example.com',
                'storeApiMcpServerUrl' => 'https://shopware.example.com/store-api/_mcp',
            ],
        ], $extension->result);
    }

    public function testSalesChannelFileContextIsNotAddedForOtherFiles(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects($this->never())
            ->method('generate');

        $extension = new SalesChannelFileRenderParametersExtension(
            $this->createSalesChannelFile('AGENTS.md'),
            $this->createSalesChannelContext(null),
            $this->createSalesChannel(
                Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
                new SalesChannelDomainCollection([$this->createDomain('https://storefront.example.com')])
            )
        );
        $extension->result = [];

        (new SalesChannelFileStoreApiMcpSubscriber($urlGenerator, self::APP_URL))->addStoreApiMcpContext($extension);

        static::assertSame([], $extension->result);
    }

    private function createSalesChannelContext(?string $domainId, string $languageId = Defaults::LANGUAGE_SYSTEM): SalesChannelContext
    {
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getDomainId')->willReturn($domainId);
        $context->method('getLanguageId')->willReturn($languageId);

        return $context;
    }

    private function createSalesChannel(string $typeId, SalesChannelDomainCollection $domains): SalesChannelEntity
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setTypeId($typeId);
        $salesChannel->setDomains($domains);

        return $salesChannel;
    }

    private function createDomain(string $url, string $languageId = Defaults::LANGUAGE_SYSTEM): SalesChannelDomainEntity
    {
        $domain = new SalesChannelDomainEntity();
        $domain->setId(Uuid::randomHex());
        $domain->setUrl($url);
        $domain->setLanguageId($languageId);

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
