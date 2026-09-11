<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\Twig\Extension\NodeExtension;
use Shopware\Core\Framework\Adapter\Twig\Runtime\CachedEscaperRuntime;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Extension\CoreExtension;
use Twig\Loader\LoaderInterface;
use Twig\Node\Node;
use Twig\Runtime\EscaperRuntime;
use Twig\Template;
use Twig\TemplateWrapper;

/**
 * @internal
 */
#[Package('framework')]
class TwigEnvironment extends Environment implements ResetInterface
{
    private ?\DateTimeZone $configuredTimezone = null;

    private ?EntityTemplateLoader $appTemplateLoader = null;

    private ?LoggerInterface $appTemplateLogger = null;

    private ?RequestStack $requestStack = null;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(LoaderInterface $loader, array $options = [])
    {
        // There is no Symfony configuration yet to toggle this feature
        $options['use_yield'] = true;

        parent::__construct($loader, $options);
    }

    public function configureAppTemplateFailureHandling(EntityTemplateLoader $loader, LoggerInterface $logger, RequestStack $requestStack): void
    {
        $this->appTemplateLoader = $loader;
        $this->appTemplateLogger = $logger;
        $this->requestStack = $requestStack;
    }

    public function isAppTemplateDisabled(string $name): bool
    {
        return $this->appTemplateLoader?->isTemplateDisabled($name) ?? false;
    }

    /**
     * @param string|TemplateWrapper $name
     * @param array<string, mixed> $context
     */
    public function render($name, array $context = []): string
    {
        if ($this->appTemplateLoader === null || !$this->hasExtension(NodeExtension::class)) {
            return parent::render($name, $context);
        }

        $disabledApps = $this->appTemplateLoader->getDisabledApps();

        try {
            while (true) {
                try {
                    // render() buffers the complete output, so a failed attempt is discarded.
                    return parent::render($name, $context);
                } catch (Error $error) {
                    $source = $error->getSourceContext()?->getName();
                    if ($source === null || !$this->appTemplateLoader->disableApp($source)) {
                        throw $error;
                    }

                    $this->appTemplateLogger?->error('Failed to render app template "{template}". Retrying without the app templates.', [
                        'template' => $source,
                        'exception' => $error,
                    ]);

                    // A context-specific failure must not put the degraded page into the HTTP cache.
                    $this->requestStack?->getCurrentRequest()?->attributes->set(PlatformRequest::ATTRIBUTE_NO_STORE, true);
                    $this->requestStack?->getMainRequest()?->attributes->set(PlatformRequest::ATTRIBUTE_NO_STORE, true);

                    if ($name instanceof TemplateWrapper) {
                        $name = $name->getTemplateName();
                    }
                }
            }
        } finally {
            $this->appTemplateLoader->setDisabledApps($disabledApps);
        }
    }

    public function getTemplateClass(string $name, ?int $index = null): string
    {
        $disabledApps = $this->appTemplateLoader?->getDisabledApps() ?? [];
        if ($disabledApps === []) {
            return parent::getTemplateClass($name, $index);
        }

        // Cached template instances retain their parents and blocks. Each retry needs a
        // separate class/cache key for the entire inheritance chain, including core templates.
        sort($disabledApps);
        $class = $this->appTemplateLoader?->isAppDisabled($name)
            ? '__TwigTemplate_' . Hasher::hash($name)
            : parent::getTemplateClass($name);

        return $class . '_' . Hasher::hash($disabledApps) . ($index === null ? '' : '___' . $index);
    }

    public function loadTemplate(string $cls, string $name, ?int $index = null): Template
    {
        if ($this->appTemplateLoader?->isAppDisabled($name) && $this->hasExtension(NodeExtension::class)) {
            $fallback = $this->getExtension(NodeExtension::class)->getFinder()->find($name, true, $name);

            if (!$this->getLoader()->exists($fallback)) {
                return $this->createTemplate('')->unwrap();
            }

            return $this->loadTemplate($this->getTemplateClass($fallback), $fallback);
        }

        return parent::loadTemplate($cls, $name, $index);
    }

    /**
     * Overrides Twig {@see CoreExtension} with SW custom wrapper {@see SwTwigFunction}.
     * Overrides Twig {@see EscaperRuntime} with SW custom wrapper {@see CachedEscaperRuntime}
     */
    public function compile(Node $node): string
    {
        $source = parent::compile($node);

        return strtr($source, [
            'CoreExtension::getAttribute(' => '\Shopware\Core\Framework\Adapter\Twig\SwTwigFunction::getAttribute(',
            '$this->env->getRuntime(\'Twig\\Runtime\\EscaperRuntime\')->escape(' => '\Shopware\Core\Framework\Adapter\Twig\Runtime\CachedEscaperRuntime::escape($this->env->getRuntime(\'Twig\\Runtime\\EscaperRuntime\'), ',
        ]);
    }

    /**
     * Resets CachedEscaperRuntime static caches between requests.
     *
     * This is essential for long runner environments (RoadRunner, FrankenPHP, Swoole)
     * where the same PHP process handles multiple requests. Without reset,
     * the escape filter cache in CachedEscaperRuntime would grow unbounded,
     * causing memory leaks.
     */
    public function reset(): void
    {
        CachedEscaperRuntime::resetEscapeCache();
    }

    /**
     * Overrides the runtime timezone, keeping the originally configured one as fallback for renderWithTimezoneOverride().
     */
    public function overrideTimezone(\DateTimeZone|string $timezone): void
    {
        if (!$this->hasExtension(CoreExtension::class)) {
            return;
        }

        $coreExtension = $this->getExtension(CoreExtension::class);
        $this->configuredTimezone ??= $coreExtension->getTimezone();
        $coreExtension->setTimezone($timezone);
    }

    /**
     * Renders a template within a temporary Twig timezone override.
     *
     * @param array<string, mixed> $context
     */
    public function renderWithTimezoneOverride(string|TemplateWrapper $name, array $context = [], \DateTimeZone|string|null $timezone = null): string
    {
        if ($timezone === '') {
            $timezone = null;
        }

        if ($timezone === null && Feature::isActive('v6.8.0.0')) {
            $timezone = $this->configuredTimezone;
        }

        if ($timezone === null || !$this->hasExtension(CoreExtension::class)) {
            return $this->render($name, $context);
        }

        $coreExtension = $this->getExtension(CoreExtension::class);
        $previous = $coreExtension->getTimezone();
        $coreExtension->setTimezone($timezone);

        try {
            return $this->render($name, $context);
        } finally {
            $coreExtension->setTimezone($previous);
        }
    }
}
