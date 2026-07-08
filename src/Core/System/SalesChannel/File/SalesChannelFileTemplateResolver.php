<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\File;

use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\File\Discovery\SalesChannelFile;
use Shopware\Core\System\SalesChannel\File\Event\SalesChannelFileTemplateResolveEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Loader\LoaderInterface;

/**
 * @internal
 */
#[Package('framework')]
class SalesChannelFileTemplateResolver
{
    public function __construct(
        private readonly TemplateFinder $templateFinder,
        private readonly NamespaceHierarchyBuilder $namespaceHierarchyBuilder,
        private readonly LoaderInterface $loader,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function getRenderTemplateName(SalesChannelFile $file, ?string $salesChannelId = null): string
    {
        $templates = $this->resolveTemplateChain($file, $salesChannelId);

        return array_first($templates) ?? $file->baseTemplateName;
    }

    public function getBaseTemplateName(SalesChannelFile $file, ?string $salesChannelId = null): string
    {
        $templates = $this->resolveTemplateChain($file, $salesChannelId);

        return array_last($templates) ?? $file->baseTemplateName;
    }

    /**
     * @return array<string, string> Twig namespace mapped to resolved template name
     */
    public function resolveTemplateChain(SalesChannelFile $file, ?string $salesChannelId = null): array
    {
        if ($salesChannelId !== null) {
            $this->eventDispatcher->dispatch(new SalesChannelFileTemplateResolveEvent($salesChannelId));
        }

        $this->templateFinder->reset();

        $templates = [];

        foreach (array_keys($this->namespaceHierarchyBuilder->buildHierarchy()) as $twigNamespace) {
            $templateName = '@' . $twigNamespace . '/' . $file->templatePath;

            if (!$this->loader->exists($templateName)) {
                continue;
            }

            $templates[$twigNamespace] = $templateName;
        }

        return $templates ?: $file->templates;
    }
}
