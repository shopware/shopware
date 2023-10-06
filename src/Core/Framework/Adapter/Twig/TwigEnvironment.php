<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Log\Package;
use Twig\Compiler;
use Twig\Environment;
use Twig\Node\Node;

/**
 * @internal
 */
#[Package('core')]
class TwigEnvironment extends Environment
{
    private ?Compiler $compiler = null;

    public function compile(Node $node): string
    {
        if ($this->compiler === null) {
            $this->compiler = new Compiler($this);
        }

        $source = $this->compiler->compile($node)->getSource();

        $source = str_replace('twig_get_attribute(', 'sw_get_attribute(', $source);
        $source = str_replace('twig_escape_filter(', 'sw_escape_filter(', $source);
        $source = str_replace('use Twig\Environment;', "use Twig\Environment;\nuse function Shopware\Core\Framework\Adapter\Twig\sw_get_attribute;\nuse function Shopware\Core\Framework\Adapter\Twig\sw_escape_filter;", $source);

        return $source;
    }
}
