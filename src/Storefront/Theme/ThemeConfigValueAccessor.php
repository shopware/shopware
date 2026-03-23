<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('framework')]
class ThemeConfigValueAccessor
{
    /**
     * Matches SCSS-only color and math functions that have no CSS equivalent.
     * Values containing these calls cannot be emitted as CSS custom properties.
     *
     * Deliberately excludes CSS-native functions (rgb, rgba, hsl, hsla, calc,
     * linear-gradient, etc.) so those are emitted unchanged.
     */
    private const SCSS_FUNCTION_PATTERN = '/\b(?:darken|lighten|saturate|desaturate|mix|adjust-hue|tint|shade|fade-in|fade-out|opacify|transparentize|invert|complement|change-color|adjust-color|scale-color|hue|saturation|lightness|red|green|blue|alpha|opacity)\s*\(/';

    /**
     * @var array<string, mixed>
     */
    private array $themeConfig = [];

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractResolvedConfigLoader $themeConfigLoader,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly ?ThemeRuntimeConfigService $themeRuntimeConfigService = null,
    ) {
    }

    /**
     * @return string|bool|array<string, mixed>|float|int|null
     */
    public function get(string $key, SalesChannelContext $context, ?string $themeId)
    {
        $config = $this->getThemeConfig($context, $themeId);

        return $config[$key] ?? null;
    }

    /**
     * Returns all theme config fields that have `"scss": true` (the default) as a flat
     * key → value map, ready for injection as CSS custom properties.
     *
     * Values are resolved the same way as `get()` — media UUIDs are replaced by their
     * public URLs and the result is cached per sales-channel/domain/theme combination.
     * Fields with `"scss": false` or type `"media"` are excluded.
     * Checkbox/switch values are cast to `0`/`1`; all others are returned as strings.
     *
     * @return array<string, string|int>
     */
    public function getCssVarValues(SalesChannelContext $context, ?string $themeId): array
    {
        if ($themeId === null || $this->themeRuntimeConfigService === null) {
            return [];
        }

        $runtimeConfig = $this->themeRuntimeConfigService->getRuntimeConfig($themeId);
        if ($runtimeConfig === null) {
            return [];
        }

        // Resolved values (media URLs already substituted, cached per SC+domain+theme).
        $resolvedValues = $this->getThemeConfig($context, $themeId);

        $result = [];

        /** @var array{fields?: array<string, array{value?: mixed, type: string, scss?: bool}>} $config */
        $config = $runtimeConfig->resolvedConfig;

        foreach ($config['fields'] ?? [] as $key => $data) {
            if (
                !\is_array($data)
                || !isset($data['type'])
                || (\array_key_exists('scss', $data) && $data['scss'] === false)
            ) {
                continue;
            }

            $value = $resolvedValues[$key] ?? null;

            if ($value === null || \is_array($value) || \is_bool($value)) {
                continue;
            }

            $type = $data['type'];

            if ($type === 'media' || $type === 'url') {
                // Skip media fields whose UUID could not be resolved to a URL.
                if (Uuid::isValid((string) $value)) {
                    continue;
                }

                // Wrap in url() so the variable can be used directly as a property value,
                // e.g. `background-image: var(--sw-logo-desktop)`.
                // CSS does not allow var() inside url(), so the token must be pre-built here.
                $result[$key] = \sprintf('url(%s)', $value);

                continue;
            }

            if ($type === 'switch' || $type === 'checkbox') {
                $result[$key] = (int) $value;

                continue;
            }

            $stringValue = (string) $value;

            // Skip values that call SCSS-only color/math functions, regardless of whether
            // their arguments are variables or literals. Examples:
            //   darken(#0042a0, 5%)                          — no CSS equivalent
            //   darken($sw-color-brand-primary, 5%)          — no CSS equivalent
            //   hsl(hue($sw-border-color), saturation(...))  — hue()/saturation() are SCSS-only
            if (preg_match(self::SCSS_FUNCTION_PATTERN, $stringValue)) {
                continue;
            }

            // Resolve bare SCSS variable references to CSS custom property references.
            //   $sw-color-brand-primary → var(--sw-color-brand-primary)
            // If a `$` reference appears inside a function call not caught above, skip it
            // rather than emitting a broken value.
            if (str_contains($stringValue, '$')) {
                if (str_contains($stringValue, '(')) {
                    continue;
                }

                $stringValue = (string) preg_replace('/\$([a-zA-Z][a-zA-Z0-9_-]*)/', 'var(--$1)', $stringValue);
            }

            $result[$key] = $stringValue;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function getThemeConfig(SalesChannelContext $context, ?string $themeId): array
    {
        $key = $context->getSalesChannelId() . $context->getDomainId() . $themeId;

        if (isset($this->themeConfig[$key])) {
            return $this->themeConfig[$key];
        }

        $themeConfig = [];

        // @deprecated tag:v6.8.0 - Obsolete. Remove with next major version.
        if (!Feature::isActive('v6.8.0.0')) {
            $themeConfig = [
                'breakpoint' => [
                    'xs' => 0,
                    'sm' => 576,
                    'md' => 768,
                    'lg' => 992,
                    'xl' => 1200,
                    'xxl' => 1400,
                ],
            ];
        }

        if (!$themeId) {
            return $this->themeConfig[$key] = $this->flatten($themeConfig, null);
        }

        $this->cacheTagCollector->addTag(ThemeConfigCacheInvalidator::buildCacheTag($themeId));

        $themeConfig = array_merge(
            $themeConfig,
            [
                'assets' => [
                    'css' => [
                        '/css/all.css',
                    ],
                    'js' => [
                        '/js/all.js',
                    ],
                ],
            ],
            $this->themeConfigLoader->load($themeId, $context)
        );

        $themeConfig = array_merge(
            $themeConfig,
            [
                'breakpoint' => [
                    'xs' => $themeConfig['sw-breakpoint-xs'] ?? 0,
                    'sm' => $themeConfig['sw-breakpoint-sm'] ?? 576,
                    'md' => $themeConfig['sw-breakpoint-md'] ?? 768,
                    'lg' => $themeConfig['sw-breakpoint-lg'] ?? 992,
                    'xl' => $themeConfig['sw-breakpoint-xl'] ?? 1200,
                    'xxl' => $themeConfig['sw-breakpoint-xxl'] ?? 1400,
                ],
            ]
        );

        return $this->themeConfig[$key] = $this->flatten($themeConfig, null);
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function flatten(array $values, ?string $prefix): array
    {
        $prefix = $prefix ? $prefix . '.' : '';
        $flat = [];
        foreach ($values as $key => $value) {
            $isNested = \is_array($value) && !isset($value[0]);

            if (!$isNested) {
                $flat[$prefix . $key] = $value;

                continue;
            }

            $nested = $this->flatten($value, $prefix . $key);
            foreach ($nested as $nestedKey => $nestedValue) {
                $flat[$nestedKey] = $nestedValue;
            }
        }

        return $flat;
    }
}
