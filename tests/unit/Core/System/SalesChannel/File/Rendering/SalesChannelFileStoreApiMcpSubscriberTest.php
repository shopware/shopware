<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\File\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
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
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('store-api.mcp.endpoint', [], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/store-api/_mcp');

        $subscriber = new SalesChannelFileStoreApiMcpSubscriber($urlGenerator);
        $extension = new SalesChannelFileRenderParametersExtension(
            new SalesChannelFile(
                'agentic',
                '.well-known/ai-catalog.json',
                'files/agentic/.well-known/ai-catalog.json.twig',
                'application/json; charset=utf-8',
                'files/agentic/.well-known/ai-catalog.json.twig',
                []
            ),
            $this->createMock(SalesChannelContext::class),
            $this->createSalesChannel()
        );
        $extension->result = [
            'salesChannelFileContext' => [
                'baseUrl' => 'https://headless.example.com/',
            ],
        ];

        $subscriber->addStoreApiMcpContext($extension);

        static::assertSame([
            'salesChannelFileContext' => [
                'baseUrl' => 'https://headless.example.com/',
                'storeApiMcpServerUrl' => 'https://headless.example.com/store-api/_mcp',
            ],
        ], $extension->result);
    }

    private function createSalesChannel(): SalesChannelEntity
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setTypeId(Defaults::SALES_CHANNEL_TYPE_API);

        return $salesChannel;
    }
}
