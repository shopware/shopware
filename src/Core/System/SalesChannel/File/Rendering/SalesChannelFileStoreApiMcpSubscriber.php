<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\File\Rendering;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
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

    private const FILE_NAMES = ['llms.txt', '.well-known/ai-catalog.json'];

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $appUrl,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SalesChannelFileRenderParametersExtension::onPost() => 'addStoreApiMcpContext',
        ];
    }

    public function addStoreApiMcpContext(SalesChannelFileRenderParametersExtension $extension): void
    {
        if ($extension->file->fileFamily !== SalesChannelFile::DEFAULT_FILE_FAMILY
            || !\in_array($extension->file->fileName, self::FILE_NAMES, true)
            || !\is_array($extension->result)
        ) {
            return;
        }

        $appUrl = rtrim($this->appUrl, '/');
        $context = ['appUrl' => $appUrl];

        $baseUrl = $this->resolveBaseUrl($extension);
        if ($baseUrl !== null) {
            $context['baseUrl'] = $baseUrl;
            $context['publisher'] = $this->extractPublisher($baseUrl);
        }

        if ($extension->salesChannel->getTypeId() === Defaults::SALES_CHANNEL_TYPE_API) {
            $path = $this->urlGenerator->generate(self::STORE_API_MCP_ROUTE, [], UrlGeneratorInterface::ABSOLUTE_PATH);
            $context['storeApiMcpServerUrl'] = $appUrl . $path;
        }

        $extension->result['salesChannelFileContext'] = $context;
    }

    private function resolveBaseUrl(SalesChannelFileRenderParametersExtension $extension): ?string
    {
        $domains = $extension->salesChannel->getDomains();
        if ($domains === null || $domains->count() === 0) {
            return null;
        }

        $domainId = $extension->context->getDomainId();
        $domain = $domainId !== null ? $domains->get($domainId) : null;

        if (!$domain instanceof SalesChannelDomainEntity) {
            $languageId = $extension->context->getLanguageId();
            $domain = $domains->firstWhere(static fn (SalesChannelDomainEntity $domain) => $domain->getLanguageId() === $languageId)
                ?? $domains->first();
        }

        return $domain instanceof SalesChannelDomainEntity ? rtrim($domain->getUrl(), '/') : null;
    }

    private function extractPublisher(string $baseUrl): ?string
    {
        $host = parse_url($baseUrl, \PHP_URL_HOST);

        return \is_string($host) && $host !== '' ? strtolower($host) : null;
    }
}
