<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\File\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelFile\SalesChannelFileEntity;
use Shopware\Core\System\SalesChannel\File\Discovery\SalesChannelFile;
use Shopware\Core\System\SalesChannel\File\Discovery\SalesChannelFileDiscovery;
use Shopware\Core\System\SalesChannel\File\Loader\SalesChannelFileConfigurationLoader;
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
    ) {
    }

    /**
     * @return list<array{fileFamily: string, fileName: string, contentType: string, configuration: array{id: string, enabled: bool, templateOverrides: array<string, string>}|null}>
     */
    public function list(string $fileFamily, string $salesChannelId, Context $context): array
    {
        $configurations = $this->configurationLoader->loadForFileFamily($fileFamily, $salesChannelId, $context);
        $files = [];

        foreach ($this->discovery->discover($fileFamily) as $file) {
            $configuration = $configurations[$file->fileName] ?? null;

            $files[] = [
                'fileFamily' => $file->fileFamily,
                'fileName' => $file->fileName,
                'contentType' => $file->contentType,
                'configuration' => $configuration === null ? null : $this->serializeConfiguration($configuration),
            ];
        }

        return $files;
    }

    /**
     * @return array{fileFamily: string, fileName: string, templatePath: string, contentType: string, templates: list<array{twigNamespace: string, templateName: string, templateContent: string, role: string}>, supportsUserProvidedContent: bool, configuration: array{id: string, enabled: bool, templateOverrides: array<string, string>}|null}|null
     */
    public function detail(string $fileFamily, string $fileName, string $salesChannelId, Context $context): ?array
    {
        $file = $this->discovery->discover($fileFamily)[$fileName] ?? null;
        if (!$file instanceof SalesChannelFile) {
            return null;
        }

        $configuration = $this->configurationLoader->load($fileFamily, $fileName, $salesChannelId, $context);

        return [
            'fileFamily' => $file->fileFamily,
            'fileName' => $file->fileName,
            'templatePath' => $file->templatePath,
            'contentType' => $file->contentType,
            'templates' => $this->serializeTemplates($file->templates),
            'supportsUserProvidedContent' => $this->supportsUserProvidedContent($file->templates),
            'configuration' => $configuration === null ? null : $this->serializeConfiguration($configuration),
        ];
    }

    /**
     * @return array{id: string, enabled: bool, templateOverrides: array<string, string>}
     */
    private function serializeConfiguration(SalesChannelFileEntity $configuration): array
    {
        return [
            'id' => $configuration->getId(),
            'enabled' => $configuration->isEnabled(),
            'templateOverrides' => $configuration->getTemplateOverrides(),
        ];
    }

    /**
     * @param array<string, string> $templates Twig namespace mapped to resolved template name
     *
     * @return list<array{twigNamespace: string, templateName: string, templateContent: string, role: string}>
     */
    private function serializeTemplates(array $templates): array
    {
        $serialized = [];
        $baseTwigNamespace = array_key_last($templates);

        foreach ($templates as $twigNamespace => $templateName) {
            $serialized[] = [
                'twigNamespace' => $twigNamespace,
                'templateName' => $templateName,
                'templateContent' => $this->loadTemplateContent($templateName),
                'role' => $twigNamespace === $baseTwigNamespace ? 'base' : 'extension',
            ];
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
