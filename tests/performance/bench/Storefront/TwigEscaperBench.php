<?php declare(strict_types=1);

namespace Shopware\Tests\Bench\Storefront;

use PhpBench\Attributes as Bench;
use Shopware\Core\Framework\Adapter\Twig\Runtime\CachedEscaperRuntime;
use Shopware\Core\Framework\Adapter\Twig\TwigEnvironment;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader;
use Twig\Loader\LoaderInterface;
use Twig\Node\Node;
use Twig\Runtime\EscaperRuntime;

/**
 * @internal - only for performance benchmarks
 */
#[Bench\Groups(['twig'])]
class TwigEscaperBench
{
    private const RENDERS_PER_ITERATION = 50;

    private const TEMPLATE = <<<'TWIG'
{% for value in values %}
    <span data-title="{{ value|e('html_attr') }}">{{ value }}</span>
{% endfor %}
TWIG;

    private TwigEnvironment $shopwareTwig;

    private LegacyRewriteTwigEnvironment $legacyRewriteTwig;

    private Environment $vanillaTwig;

    /**
     * @var array{values: list<string>}
     */
    private array $context;

    public function setUp(): void
    {
        CachedEscaperRuntime::resetEscapeCache();
        LegacyCachedEscaperRuntime::resetEscapeCache();

        $this->shopwareTwig = new TwigEnvironment(new ArrayLoader(['bench' => self::TEMPLATE]));
        $this->legacyRewriteTwig = new LegacyRewriteTwigEnvironment(new ArrayLoader(['bench' => self::TEMPLATE]));
        $this->vanillaTwig = new Environment(new ArrayLoader(['bench' => self::TEMPLATE]), ['use_yield' => true]);

        $this->context = [
            'values' => array_merge(
                array_fill(0, 150, '<script>alert("promo")</script>'),
                array_fill(0, 150, 'Summer & Winter 205/55 R16')
            ),
        ];

        $this->shopwareTwig->load('bench');
        $this->legacyRewriteTwig->load('bench');
        $this->vanillaTwig->load('bench');
    }

    #[Bench\BeforeMethods(['setUp'])]
    public function bench_render_with_shopware_cached_escaper(): void
    {
        for ($i = 0; $i < self::RENDERS_PER_ITERATION; ++$i) {
            $this->shopwareTwig->render('bench', $this->context);
        }
    }

    #[Bench\BeforeMethods(['setUp'])]
    public function bench_render_with_legacy_rewrite_cached_escaper(): void
    {
        for ($i = 0; $i < self::RENDERS_PER_ITERATION; ++$i) {
            $this->legacyRewriteTwig->render('bench', $this->context);
        }
    }

    #[Bench\BeforeMethods(['setUp'])]
    public function bench_render_with_vanilla_escaper(): void
    {
        for ($i = 0; $i < self::RENDERS_PER_ITERATION; ++$i) {
            $this->vanillaTwig->render('bench', $this->context);
        }
    }
}

/**
 * @internal
 */
final class LegacyRewriteTwigEnvironment extends Environment
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(LoaderInterface $loader, array $options = [])
    {
        $options['use_yield'] = true;

        parent::__construct($loader, $options);
    }

    public function compile(Node $node): string
    {
        $source = parent::compile($node);

        return strtr($source, [
            '$this->env->getRuntime(\'Twig\\Runtime\\EscaperRuntime\')->escape(' => '\Shopware\Tests\Bench\Storefront\LegacyCachedEscaperRuntime::escape($this->env->getRuntime(\'Twig\\Runtime\\EscaperRuntime\'), ',
        ]);
    }
}

/**
 * @internal
 */
final class LegacyCachedEscaperRuntime
{
    /**
     * @var array<string, mixed>
     */
    private static array $escapeCache = [];

    private function __construct()
    {
    }

    /**
     * @throws RuntimeError
     */
    public static function escape(
        EscaperRuntime $originalEscaperRuntime,
        mixed $string,
        string $strategy = 'html',
        ?string $charset = null,
        bool $autoescape = false
    ): mixed {
        $cacheKey = null;

        if (\is_string($string)) {
            $cacheKey = \sprintf('%s-%s-%s', $string, $strategy, $charset);
            if (isset(self::$escapeCache[$cacheKey])) {
                return self::$escapeCache[$cacheKey];
            }
        }

        $result = $originalEscaperRuntime->escape($string, $strategy, $charset, $autoescape);

        if ($cacheKey === null) {
            return $result;
        }

        self::$escapeCache[$cacheKey] = $result;

        return $result;
    }

    public static function resetEscapeCache(): void
    {
        self::$escapeCache = [];
    }
}
