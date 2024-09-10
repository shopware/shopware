<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Adapter\Cache\Event\AddCacheTagEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('storefront')]
class ThemeConfigValueAccessor
{
    /**
     * @var array<string, mixed>
     */
    private array $themeConfig = [];

    /**
     * @var array<string, bool>
     */
    private array $keys = ['all' => true];

    /**
     * @deprecated tag:v6.7.0 - Will be removed, cache tags are collected via events
     *
     * @var array<string, array<string, bool>>
     */
    private array $traces = [];

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractResolvedConfigLoader $themeConfigLoader,
        private readonly bool $fineGrainedCache,
        private readonly EventDispatcherInterface $dispatcher
    ) {
    }

    /**
     * @deprecated tag:v6.7.0 - Will be removed, use \Shopware\Storefront\Theme\CachedResolvedConfigLoader::buildName
     */
    public static function buildName(string $key): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.7.0.0')
        );

        return 'theme.' . $key;
    }

    /**
     * @return string|bool|array<string, mixed>|float|int|null
     */
    public function get(string $key, SalesChannelContext $context, ?string $themeId)
    {
        // @deprecated tag:v6.7.0 - remove full IF condition, tagging is done in `getThemeConfig`
        if (!Feature::isActive('cache_rework')) {
            if ($this->fineGrainedCache) {
                foreach (array_keys($this->keys) as $trace) {
                    $this->traces[$trace][self::buildName($key)] = true;
                }
            } else {
                foreach (array_keys($this->keys) as $trace) {
                    $this->traces[$trace]['shopware.theme'] = true;
                }
            }
        }

        $config = $this->getThemeConfig($context, $themeId);

        if (\array_key_exists($key, $config)) {
            return $config[$key];
        }

        return null;
    }

    /**
     * @deprecated tag:v6.7.0 - reason:decoration-will-be-removed - Will be removed, cache tags are collected via events
     *
     * @template TReturn of mixed
     *
     * @param \Closure(): TReturn $param
     *
     * @return TReturn All kind of data could be cached
     */
    public function trace(string $key, \Closure $param)
    {
        $this->traces[$key] = [];
        $this->keys[$key] = true;

        $result = $param();

        unset($this->keys[$key]);

        return $result;
    }

    /**
     * @return array<int, string>
     */
    public function getTrace(string $key): array
    {
        $trace = isset($this->traces[$key]) ? array_keys($this->traces[$key]) : [];
        unset($this->traces[$key]);

        return $trace;
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

        if (!$themeId) {
            return $this->themeConfig[$key] = $this->flatten($themeConfig, null);
        }

        $this->dispatcher->dispatch(new AddCacheTagEvent(
            CachedResolvedConfigLoader::buildName($themeId)
        ));

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
