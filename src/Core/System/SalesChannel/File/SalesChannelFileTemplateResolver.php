<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\File;

use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\File\Discovery\SalesChannelFile;
use Twig\Error\LoaderError;

/**
 * @internal
 */
#[Package('framework')]
class SalesChannelFileTemplateResolver
{
    public function __construct(private readonly TemplateFinder $templateFinder)
    {
    }

    public function getRenderTemplateName(SalesChannelFile $file): string
    {
        $templates = $this->resolveTemplateChain($file);
        $key = array_key_last($templates);

        return $key === null ? $file->baseTemplateName : $templates[$key];
    }

    public function getBaseTemplateName(SalesChannelFile $file): string
    {
        $templates = $this->resolveTemplateChain($file);
        $key = array_key_first($templates);

        return $key === null ? $file->baseTemplateName : $templates[$key];
    }

    /**
     * @return list<string>
     */
    private function resolveTemplateChain(SalesChannelFile $file): array
    {
        $templates = [];
        $seen = [];
        $source = null;

        // Feed each match back as source so TemplateFinder walks the same namespace chain sw_extends would resolve.
        // Stop if the hierarchy cycles back to a template we already visited.
        while (true) {
            try {
                $templateName = $this->templateFinder->find($file->templatePath, false, $source);
            } catch (LoaderError) {
                break;
            }

            if (isset($seen[$templateName])) {
                break;
            }

            $templates[] = $templateName;
            $seen[$templateName] = true;
            $source = $templateName;
        }

        return $templates ?: array_values($file->templates);
    }
}
