<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\File\Loader;

use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\File\Discovery\SalesChannelFileDiscovery;
use Shopware\Core\System\SalesChannel\File\Rendering\SalesChannelFileRenderer;
use Shopware\Core\System\SalesChannel\File\Rendering\SalesChannelFileRenderResult;
use Shopware\Core\System\SalesChannel\File\SalesChannelFileCacheInvalidator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('framework')]
class SalesChannelFileLoader
{
    public function __construct(
        private readonly SalesChannelFileDiscovery $discovery,
        private readonly SalesChannelFileConfigurationLoader $configurationLoader,
        private readonly SalesChannelFileRenderer $renderer,
        private readonly CacheTagCollector $cacheTagCollector,
    ) {
    }

    public function load(string $templatePath, SalesChannelContext $context): ?SalesChannelFileRenderResult
    {
        $file = $this->discovery->get($templatePath);
        if ($file === null) {
            return null;
        }

        $configuration = $this->configurationLoader->load(
            $file->fileFamily,
            $file->fileName,
            $context->getSalesChannelId(),
            $context->getContext()
        );
        if ($configuration === null || !$configuration->isEnabled()) {
            return null;
        }

        $this->cacheTagCollector->addTag(
            SalesChannelFileCacheInvalidator::buildCacheTag($configuration->getId()),
        );

        return new SalesChannelFileRenderResult(
            $file->fileName,
            $this->renderer->render($file, $context, $configuration->getTemplateOverrides()),
            $file->contentType,
        );
    }

    /**
     * @param array<string, mixed> $templateOverrides
     */
    public function preview(string $templatePath, SalesChannelContext $context, array $templateOverrides): ?SalesChannelFileRenderResult
    {
        $file = $this->discovery->get($templatePath);
        if ($file === null) {
            return null;
        }

        return new SalesChannelFileRenderResult(
            $file->fileName,
            $this->renderer->render($file, $context, $templateOverrides),
            $file->contentType,
        );
    }
}
