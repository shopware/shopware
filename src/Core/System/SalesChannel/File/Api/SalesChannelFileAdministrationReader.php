<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\File\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelFile\SalesChannelFileEntity;
use Shopware\Core\System\SalesChannel\File\Discovery\SalesChannelFile;
use Shopware\Core\System\SalesChannel\File\Discovery\SalesChannelFileDiscovery;
use Shopware\Core\System\SalesChannel\File\Loader\SalesChannelFileConfigurationLoader;
use Shopware\Core\System\SalesChannel\File\SalesChannelFileTemplateResolver;
use Twig\Environment;
use Twig\Error\LoaderError;

/**
 * @internal
 */
#[Package('framework')]
class SalesChannelFileAdministrationReader
{
    private const USER_PROVIDED_CONTENT_BLOCK = 'user_provided_content';

    public function __construct(
        private readonly SalesChannelFileDiscovery $discovery,
        private readonly SalesChannelFileConfigurationLoader $configurationLoader,
        private readonly Environment $twig,
        private readonly SalesChannelFileTemplateResolver $templateResolver,
    ) {
    }

    /**
     * @return list<SalesChannelFileAdministrationListItem>
     */
    public function list(string $fileFamily, string $salesChannelId, Context $context): array
    {
        $configurations = $this->configurationLoader->loadForFileFamily($fileFamily, $salesChannelId, $context);
        $files = [];

        foreach ($this->discovery->discover($fileFamily) as $file) {
            $configuration = $configurations[$file->fileName] ?? null;

            $files[] = new SalesChannelFileAdministrationListItem(
                $file->fileFamily,
                $file->fileName,
                $file->contentType,
                $configuration === null ? null : $this->serializeConfiguration($configuration),
            );
        }

        return $files;
    }

    public function detail(string $fileFamily, string $fileName, string $salesChannelId, Context $context): ?SalesChannelFileAdministrationDetail
    {
        $file = $this->discovery->discover($fileFamily)[$fileName] ?? null;
        if (!$file instanceof SalesChannelFile) {
            return null;
        }

        $configuration = $this->configurationLoader->load($fileFamily, $fileName, $salesChannelId, $context);
        $templates = $this->templateResolver->resolveTemplateChain($file, $salesChannelId);

        return new SalesChannelFileAdministrationDetail(
            $file->fileFamily,
            $file->fileName,
            $file->templatePath,
            $file->contentType,
            $this->serializeTemplates($templates, $file->baseTemplateName),
            $this->supportsUserProvidedContent($templates),
            $configuration === null ? null : $this->serializeConfiguration($configuration),
        );
    }

    private function serializeConfiguration(SalesChannelFileEntity $configuration): SalesChannelFileAdministrationConfiguration
    {
        return new SalesChannelFileAdministrationConfiguration(
            $configuration->getId(),
            $configuration->isEnabled(),
            $configuration->getTemplateOverrides(),
        );
    }

    /**
     * @param array<string, string> $templates Twig namespace mapped to resolved template name
     *
     * @return list<SalesChannelFileAdministrationTemplate>
     */
    private function serializeTemplates(array $templates, string $baseTemplateName): array
    {
        $serialized = [];
        $baseTemplateName = array_last($templates) ?? $baseTemplateName;

        foreach ($templates as $twigNamespace => $templateName) {
            $serialized[] = new SalesChannelFileAdministrationTemplate(
                $twigNamespace,
                $templateName,
                $this->loadTemplateContent($templateName),
                $templateName === $baseTemplateName ? 'base' : 'extension',
            );
        }

        return $serialized;
    }

    private function loadTemplateContent(string $templateName): string
    {
        try {
            return $this->twig->getLoader()->getSourceContext($templateName)->getCode();
        } catch (LoaderError) {
            return '';
        }
    }

    /**
     * @param array<string, string> $templates Twig namespace mapped to resolved template name
     */
    private function supportsUserProvidedContent(array $templates): bool
    {
        foreach ($templates as $templateName) {
            $source = $this->loadTemplateContent($templateName);

            if (preg_match('/{%-?\s*block\s+' . preg_quote(self::USER_PROVIDED_CONTENT_BLOCK, '/') . '\b/', $source) === 1) {
                return true;
            }
        }

        return false;
    }
}
