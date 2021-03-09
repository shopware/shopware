<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ThemeConfigValueAccessor
{
    private AbstractResolvedConfigLoader $themeConfigLoader;

    private array $themeConfig = [];

    private array $keys = ['all' => true];

    private array $traces = [];

    public function __construct(AbstractResolvedConfigLoader $themeConfigLoader)
    {
        $this->themeConfigLoader = $themeConfigLoader;
    }

    public static function buildName(string $key): string
    {
        return 'theme.' . $key;
    }

    public function get(string $key, SalesChannelContext $context, ?string $themeId)
    {
        foreach (array_keys($this->keys) as $trace) {
            $this->traces[$trace][self::buildName($key)] = true;
        }

        $config = $this->getThemeConfig($context, $themeId);

        if (\array_key_exists($key, $config)) {
            return $config[$key];
        }

        return null;
    }

    public function trace(string $key, \Closure $param)
    {
        $this->traces[$key] = [];
        $this->keys[$key] = true;

        $result = $param();

        unset($this->keys[$key]);

        return $result;
    }

    public function getTrace(string $key): array
    {
        $trace = isset($this->traces[$key]) ? array_keys($this->traces[$key]) : [];
        unset($this->traces[$key]);

        return $trace;
    }

    private function getThemeConfig(SalesChannelContext $context, ?string $themeId): array
    {
        $key = $context->getSalesChannelId() . $context->getDomainId() . $themeId;

        if (isset($this->themeConfig[$key])) {
            return $this->themeConfig[$key];
        }

        $themeConfig = [
            'breakpoint' => [
                'xs' => 0,
                'sm' => 576,
                'md' => 768,
                'lg' => 992,
                'xl' => 1200,
            ],
        ];

        if (!$themeId) {
            return $this->themeConfig[$key] = $this->flatten($themeConfig, null);
        }

        $themePrefix = ThemeCompiler::getThemePrefix($context->getSalesChannelId(), $themeId);

        $themeConfig = array_merge(
            $themeConfig,
            [
                'assets' => [
                    'css' => [
                        'theme/' . $themePrefix . '/css/all.css',
                    ],
                    'js' => [
                        'theme/' . $themePrefix . '/js/all.js',
                    ],
                ],
            ],
            $this->themeConfigLoader->load($themeId, $context)
        );

        return $this->themeConfig[$key] = $this->flatten($themeConfig, null);
    }

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
