<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\File\Rendering;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\File\Discovery\SalesChannelFile;
use Shopware\Core\System\SalesChannel\File\Rendering\Extension\SalesChannelFileRenderParametersExtension;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal
 */
#[Package('framework')]
final class SalesChannelFileStoreApiMcpSubscriber implements EventSubscriberInterface
{
    private const STORE_API_MCP_ROUTE = 'store-api.mcp.endpoint';

    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SalesChannelFileRenderParametersExtension::onPost() => 'addStoreApiMcpContext',
        ];
    }

    public function addStoreApiMcpContext(SalesChannelFileRenderParametersExtension $extension): void
    {
        if (!Feature::isActive('MCP_SERVER')
            || $extension->file->fileFamily !== SalesChannelFile::DEFAULT_FILE_FAMILY
            || $extension->file->fileName !== '.well-known/ai-catalog.json'
            || $extension->salesChannel->getTypeId() !== Defaults::SALES_CHANNEL_TYPE_API
            || !\is_array($extension->result)
        ) {
            return;
        }

        $context = $extension->result['salesChannelFileContext'] ?? null;
        if (!\is_array($context)) {
            return;
        }

        $baseUrl = $context['baseUrl'] ?? null;
        if (!\is_string($baseUrl) || $baseUrl === '') {
            return;
        }

        $path = $this->urlGenerator->generate(self::STORE_API_MCP_ROUTE, [], UrlGeneratorInterface::ABSOLUTE_PATH);
        $context['storeApiMcpServerUrl'] = rtrim($baseUrl, '/') . $path;
        $extension->result['salesChannelFileContext'] = $context;
    }
}
