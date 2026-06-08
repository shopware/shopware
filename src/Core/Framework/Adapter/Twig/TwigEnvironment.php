<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Adapter\Twig\Runtime\CachedEscaperRuntime;
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
     * Renders a template within a temporary Twig timezone override.
     *
     * @param array<string, mixed> $context
     */
    public function renderWithTimezoneOverride(string|TemplateWrapper $name, array $context = [], \DateTimeZone|string|null $timezone = null): string
    {
        if ($timezone === null || $timezone === '' || !$this->hasExtension(CoreExtension::class)) {
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
