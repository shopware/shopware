<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Log\Package;
use Twig\Compiler;
use Twig\Environment;
use Twig\Loader\LoaderInterface;
use Twig\Node\Node;

/**
 * @internal
 */
#[Package('core')]
class TwigEnvironment extends Environment
{
    /**
     * @param array<mixed> $options
     */
    public function __construct(LoaderInterface $loader, $options = [])
    {
        // There is no Symfony configuration yet to toggle this feature
        $options['use_yield'] = true;

        parent::__construct($loader, $options);
    }

    private ?Compiler $compiler = null;

    public function compile(Node $node): string
    {
        if ($this->compiler === null) {
            $this->compiler = new Compiler($this);
        }

        $source = $this->compiler->compile($node)->getSource();

        $source = str_replace('CoreExtension::getAttribute(', 'SwTwigFunction::getAttribute(', $source);
        $source = str_replace('CoreExtension::callMacro(', 'SwTwigFunction::callMacro(', $source);
        $source = str_replace('twig_escape_filter(', 'SwTwigFunction::escapeFilter(', $source);
        $source = str_replace('use Twig\Environment;', "use Twig\Environment;\nuse Shopware\Core\Framework\Adapter\Twig\SwTwigFunction;", $source);

        return $source;
    }
}
