<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Extension;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\SalesChannelRequest;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

#[Package('framework')]
class ThemeFilePathsExtension extends AbstractExtension
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly string $projectDir,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('theme_source_files', $this->getThemeSourceFiles(...)),
        ];
    }

    /**
     * Get source file paths from theme-files.json for dev server mode
     *
     * @return array<array{filepath: string, assetName: string}>
     */
    public function getThemeSourceFiles(): array
    {
        $request = $this->requestStack->getMainRequest();

        if ($request === null) {
            return [];
        }

        $themeId = $request->attributes->get(SalesChannelRequest::ATTRIBUTE_THEME_ID);

        if ($themeId === null) {
            return [];
        }

        $themeFilesPath = $this->projectDir . '/var/theme-files.json';

        if (!file_exists($themeFilesPath)) {
            return [];
        }

        $themeFiles = json_decode((string) file_get_contents($themeFilesPath), true);

        if (!isset($themeFiles['script']) || !\is_array($themeFiles['script'])) {
            return [];
        }

        return $themeFiles['script'];
    }
}
