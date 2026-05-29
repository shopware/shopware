<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\File\Rendering;

use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\File\Discovery\SalesChannelFile;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Twig\Environment;

/**
 * @internal
 */
#[Package('framework')]
class SalesChannelFileRenderer
{
    public function __construct(
        private readonly Environment $twig,
        private readonly TemplateFinder $templateFinder,
        private readonly SalesChannelFileTemplateOverrideLoader $templateOverrideLoader,
    ) {
    }

    /**
     * @param array<string, mixed> $templateOverrides
     */
    public function render(SalesChannelFile $file, SalesChannelContext $context, array $templateOverrides = []): string
    {
        $overrideTemplates = $this->buildOverrideTemplates($file, $templateOverrides);
        $parameters = $this->buildParameters($file, $context);

        return $this->templateOverrideLoader->withTemplateOverrides(
            $overrideTemplates,
            fn (): string => $this->twig->render(
                $this->templateFinder->find($file->getBaseTemplateName()),
                $parameters
            )
        );
    }

    /**
     * @param array<string, mixed> $templateOverrides
     *
     * @return array<string, string>
     */
    private function buildOverrideTemplates(SalesChannelFile $file, array $templateOverrides): array
    {
        $overrideTemplates = [];

        foreach ($file->getTemplates() as $twigNamespace => $templateName) {
            $override = $templateOverrides[$twigNamespace] ?? null;

            if (!\is_string($override)) {
                continue;
            }

            $overrideTemplates[$templateName] = $override;
        }

        return $overrideTemplates;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildParameters(SalesChannelFile $file, SalesChannelContext $context): array
    {
        return [
            'context' => $context,
            'salesChannel' => $context->getSalesChannel(),
            'salesChannelFile' => $file,
        ];
    }
}
