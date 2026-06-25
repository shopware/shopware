<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Adapter\Twig\Runtime\CachedEscaperRuntime;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Extension\CoreExtension;
use Twig\Loader\LoaderInterface;
use Twig\Node\Node;
use Twig\Runtime\EscaperRuntime;
use Twig\TemplateWrapper;

/**
 * @internal
 */
#[Package('framework')]
class TwigEnvironment extends Environment
{
    private ?\DateTimeZone $configuredTimezone = null;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(LoaderInterface $loader, array $options = [])
    {
        // There is no Symfony configuration yet to toggle this feature
        $options['use_yield'] = true;

        parent::__construct($loader, $options);
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
