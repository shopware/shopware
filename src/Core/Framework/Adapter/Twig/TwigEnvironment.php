<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Profiler;
use Twig\Compiler;
use Twig\Environment;
use Twig\Loader\LoaderInterface;
use Twig\Node\Node;
use Twig\Template;

/**
 * @internal
 */
#[Package('core')]
class TwigEnvironment extends Environment
{
    private ?Compiler $compiler = null;

    /**
     * @param array<mixed> $options
     */
    public function __construct(LoaderInterface $loader, array $options = [])
    {
        // There is no Symfony configuration yet to toggle this feature
        $options['use_yield'] = true;

        parent::__construct($loader, $options);
    }

    public function loadTemplate(string $cls, string $name, ?int $index = null): Template
    {
        return Profiler::trace($name, function () use ($cls, $name, $index) {
            return parent::loadTemplate($cls, $name, $index);
        }, 'sw-template');
    }

    public function compile(Node $node): string
    {
        if ($this->compiler === null) {
            $this->compiler = new Compiler($this);
        }

        $source = $this->compiler->compile($node)->getSource();

        $replaces = [
            'CoreExtension::getAttribute(' => 'SwTwigFunction::getAttribute(',
            'CoreExtension::callMacro(' => 'SwTwigFunction::callMacro(',
            'twig_escape_filter(' => 'SwTwigFunction::escapeFilter(',
            'use Twig\Environment;' => "use Twig\Environment;\nuse Shopware\Core\Framework\Adapter\Twig\SwTwigFunction;",
        ];

        return str_replace(array_keys($replaces), array_values($replaces), $source);
    }
}
