<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Adapter\Twig\Runtime\CachedEscaperRuntime;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\LoaderInterface;
use Twig\Node\Node;
use Twig\Runtime\EscaperRuntime;
use Twig\RuntimeLoader\RuntimeLoaderInterface;

/**
 * @internal
 */
#[Package('framework')]
class TwigEnvironment extends Environment
{
    private ?CachedEscaperRuntime $escaperRuntime = null;

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
     * Wraps the original method to inject the {@see CachedEscaperRuntime} into the Twig system.
     * It is not possible to introduce a new {@see RuntimeLoaderInterface} with {@see Environment::addRuntimeLoader()},
     * as the internal cache key is the class name, which cannot be influenced.
     *
     * @template TRuntime of object
     *
     * @param class-string<TRuntime> $class
     *
     * @throws RuntimeError
     *
     * @return ($class is class-string<EscaperRuntime> ? CachedEscaperRuntime : TRuntime)
     */
    public function getRuntime(string $class): object
    {
        if ($class !== EscaperRuntime::class) {
            return parent::getRuntime($class);
        }

        if ($this->escaperRuntime !== null) {
            return $this->escaperRuntime;
        }

        // It is safe to instantiate the original `EscaperRuntime` directly, as runtimes are cached by their FQCNs.
        // This means it is not possible for Twig extensions to overwrite this by registering custom runtime loaders.
        // Else this would have been done and not this trick instead.
        // Additionally, this is faster than calling the original `getRuntime` method to get the `EscaperRuntime` instance.
        $this->escaperRuntime = new CachedEscaperRuntime(new EscaperRuntime($this->getCharset()));

        return $this->escaperRuntime;
    }

    /**
     * Overrides Twig CoreExtension with SW custom wrapper {@see SwTwigFunction}
     */
    public function compile(Node $node): string
    {
        $source = parent::compile($node);

        return str_replace('CoreExtension::getAttribute(', '\Shopware\Core\Framework\Adapter\Twig\SwTwigFunction::getAttribute(', $source);
    }
}
