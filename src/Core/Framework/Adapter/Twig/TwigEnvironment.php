<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Adapter\Twig\Runtime\CachedEscaperRuntime;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Loader\LoaderInterface;
use Twig\Node\Node;
use Twig\Runtime\EscaperRuntime;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

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

        $this->addRuntimeLoader(new FactoryRuntimeLoader([
            EscaperRuntime::class => fn () => new CachedEscaperRuntime($this->getCharset()),
        ]));
    }

    /**
     * Overrides Twig {@see CoreExtension} with SW custom wrapper {@see SwTwigFunction}.
     */
    public function compile(Node $node): string
    {
        $source = parent::compile($node);

        return strtr($source, [
            'CoreExtension::getAttribute(' => '\Shopware\Core\Framework\Adapter\Twig\SwTwigFunction::getAttribute(',
        ]);
    }
}
