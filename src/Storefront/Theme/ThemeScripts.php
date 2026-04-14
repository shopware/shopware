<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
readonly class ThemeScripts
{
    /**
     * @internal
     */
    public function __construct(
        private RequestStack $requestStack,
        private ThemeRuntimeConfigService $themeRuntimeConfigService,
        private readonly FilesystemOperator $tempFilesystem,
    ) {
    }

    /**
     * @return array<string>
     */
    public function getThemeScripts(): array
    {
        $runtimeConfig = $this->getThemeRuntimeConfig();

        if ($runtimeConfig?->scriptFiles === null) {
            return [];
        }

        return $runtimeConfig->scriptFiles;
    }

    /**
     * Returns the pre-built component import map stored in the runtime config, or null when
     * no import map has been compiled yet (first-run / test environment without a build).
     *
     * Paths inside the map are theme-relative (e.g. 'js/components/Sw/Filter/Sorting.js').
     * TemplateConfigAccessor converts them to full URLs at request time.
     *
     * @return array{imports: array<string, string>, scopes?: array<string, array<string, string>>}|null
     */
    public function getComponentImportMap(): ?array
    {
        return $this->getThemeRuntimeConfig()?->componentImportMap;
    }

    /**
     * Returns the dev import map written by the Vite component dev server, or
     * null when no dev server is running.
     *
     * The file lives at `cache/storefront_components.dev.json` within the
     * `shopware.filesystem.temp` filesystem (rooted at `var/`).
     *
     * @return array{imports: array<string, string>}|null
     */
    public function getDevImportMap(): ?array
    {
        // Path relative to the temp filesystem root (var/).
        $flagPath = 'cache/storefront_components.dev.json';

        try {
            if (!$this->tempFilesystem->fileExists($flagPath)) {
                return null;
            }

            $json = $this->tempFilesystem->read($flagPath);

            /** @var array{imports: array<string, string>}|null $map */
            $map = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

            return \is_array($map) ? $map : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function getThemeRuntimeConfig(): ?ThemeRuntimeConfig
    {
        $request = $this->requestStack->getMainRequest();

        if ($request === null) {
            return null;
        }

        $themeId = $request->attributes->get(SalesChannelRequest::ATTRIBUTE_THEME_ID);

        if ($themeId === null) {
            return null;
        }

        $salesChannelContext = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        if (!$salesChannelContext instanceof SalesChannelContext) {
            return null;
        }

        return $this->themeRuntimeConfigService->getResolvedRuntimeConfig($themeId);
    }
}
