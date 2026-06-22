<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\SalesChannelRequest;
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
        private FilesystemOperator $tempFilesystem,
        private LoggerInterface $logger,
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
     * Returns the pre-built import map stored in the runtime config, or null when
     * no import map has been compiled yet (first-run / test environment without a build).
     *
     * Paths inside the map are theme-relative (e.g. 'js/components/Sw/Filter/Sorting.js').
     * TemplateConfigAccessor converts them to full URLs at request time.
     *
     * @return array{imports: array<string, string>, scopes?: array<string, array<string, string>>, styles?: list<string>}|null
     */
    public function getImportMap(): ?array
    {
        return $this->getThemeRuntimeConfig()?->importMap;
    }

    /**
     * Returns the dev flag file written by the Vite component dev server, or
     * null when no dev server is running.
     *
     * The file lives at `cache/storefront_components.dev.json` within the
     * `shopware.filesystem.temp` filesystem (rooted at `var/`).
     *
     * Structure written by dev-import-map-plugin:
     *   imports  — ES module import map (component tags → dev-server URLs)
     *   styles   — ordered CSS URLs served by the sw-theme-scss middleware
     *
     * @return array{imports: array<string, string>, styles?: list<string>, scripts?: list<string>, themeId?: string}|null
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

            /** @var array{imports: array<string, string>, styles?: list<string>, scripts?: list<string>, themeId?: string}|null $map */
            $map = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

            if (!\is_array($map)) {
                return null;
            }

            $devThemeId = $map['themeId'] ?? null;
            if (!\is_string($devThemeId)) {
                return $map;
            }

            $requestThemeId = $this->getCurrentRequestThemeId();

            if ($requestThemeId !== null && $requestThemeId !== $devThemeId) {
                $this->logger->debug('Storefront dev import map skipped due to theme mismatch.', [
                    'requestThemeId' => $requestThemeId,
                    'devThemeId' => $devThemeId,
                    'path' => $flagPath,
                ]);

                return null;
            }

            return $map;
        } catch (\Throwable $exception) {
            $this->logger->warning('Invalid storefront dev import map; skipping dev import map integration.', [
                'path' => $flagPath,
                'exception' => $exception,
            ]);

            return null;
        }
    }

    private function getThemeRuntimeConfig(): ?ThemeRuntimeConfig
    {
        if ($this->requestStack->getMainRequest() === null) {
            return null;
        }

        $themeId = $this->getCurrentRequestThemeId();
        if ($themeId === null) {
            return null;
        }

        return $this->themeRuntimeConfigService->getResolvedRuntimeConfig($themeId);
    }

    private function getCurrentRequestThemeId(): ?string
    {
        $request = $this->requestStack->getMainRequest();

        if ($request === null) {
            return null;
        }

        $themeId = $request->attributes->get(SalesChannelRequest::ATTRIBUTE_THEME_ID);

        return \is_string($themeId) ? $themeId : null;
    }
}
