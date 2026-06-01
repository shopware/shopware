<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\File\Loader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelFile\SalesChannelFileEntity;
use Shopware\Core\System\SalesChannel\File\Discovery\SalesChannelFile;
use Shopware\Core\System\SalesChannel\File\Discovery\SalesChannelFileDiscovery;
use Shopware\Core\System\SalesChannel\File\Loader\SalesChannelFileConfigurationLoader;
use Shopware\Core\System\SalesChannel\File\Loader\SalesChannelFileLoader;
use Shopware\Core\System\SalesChannel\File\Rendering\SalesChannelFileRenderer;
use Shopware\Core\System\SalesChannel\File\SalesChannelFileCacheInvalidator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SalesChannelFileLoader::class)]
class SalesChannelFileLoaderTest extends TestCase
{
    public function testItTagsRenderedFilesWithSalesChannelFileConfigurationId(): void
    {
        $templatePath = 'files/agentic/llms.txt.twig';
        $salesChannelId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $configurationId = Uuid::randomHex();
        $file = new SalesChannelFile(
            'agentic',
            'llms.txt',
            'files/agentic/llms.txt.twig',
            'text/plain; charset=utf-8',
            'files/agentic/llms.txt.twig',
            [
                'Framework' => '@Framework/files/agentic/llms.txt.twig',
            ],
        );
        $configuration = new SalesChannelFileEntity();
        $configuration->setId($configurationId);
        $configuration->setSalesChannelId($salesChannelId);
        $configuration->setFileFamily('agentic');
        $configuration->setFileName('llms.txt');
        $configuration->setEnabled(true);
        $configuration->setTemplateOverrides(['Framework' => 'merchant override']);

        $discovery = $this->createMock(SalesChannelFileDiscovery::class);
        $discovery
            ->expects($this->once())
            ->method('get')
            ->with($templatePath)
            ->willReturn($file);

        $configurationLoader = $this->createMock(SalesChannelFileConfigurationLoader::class);
        $configurationLoader
            ->expects($this->once())
            ->method('load')
            ->with('agentic', 'llms.txt', $salesChannelId, $context)
            ->willReturn($configuration);

        $renderer = $this->createMock(SalesChannelFileRenderer::class);
        $renderer
            ->expects($this->once())
            ->method('render')
            ->with($file, static::isInstanceOf(SalesChannelContext::class), ['Framework' => 'merchant override'])
            ->willReturn('rendered content');

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector
            ->expects($this->once())
            ->method('addTag')
            ->with(SalesChannelFileCacheInvalidator::buildCacheTag($configurationId));

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn($salesChannelId);
        $salesChannelContext->method('getContext')->willReturn($context);

        $result = (new SalesChannelFileLoader($discovery, $configurationLoader, $renderer, $cacheTagCollector))->load($templatePath, $salesChannelContext);

        static::assertNotNull($result);
        static::assertSame('llms.txt', $result->fileName);
        static::assertSame('rendered content', $result->content);
        static::assertSame('text/plain; charset=utf-8', $result->contentType);
    }

    public function testItDoesNotLoadSalesChannelFileConfigurationForUnknownDiscoveredFile(): void
    {
        $templatePath = 'files/agentic/unknown.txt.twig';

        $discovery = $this->createMock(SalesChannelFileDiscovery::class);
        $discovery
            ->expects($this->once())
            ->method('get')
            ->with($templatePath)
            ->willReturn(null);

        $configurationLoader = $this->createMock(SalesChannelFileConfigurationLoader::class);
        $configurationLoader
            ->expects($this->never())
            ->method('load');

        $renderer = $this->createMock(SalesChannelFileRenderer::class);
        $renderer
            ->expects($this->never())
            ->method('render');

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector
            ->expects($this->never())
            ->method('addTag');

        $result = (new SalesChannelFileLoader(
            $discovery,
            $configurationLoader,
            $renderer,
            $cacheTagCollector
        ))->load($templatePath, $this->createMock(SalesChannelContext::class));

        static::assertNull($result);
    }
}
