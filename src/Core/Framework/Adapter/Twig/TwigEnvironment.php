<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Adapter\Twig\Runtime\CachedEscaperRuntime;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Loader\LoaderInterface;
use Twig\Node\Node;
use Twig\Runtime\EscaperRuntime;

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

    public function getRuntime(string $class)
    {
        if ($class === EscaperRuntime::class) {
            if ($this->escaperRuntime !== null) {
                /** @phpstan-ignore return.type (There is no other way to decorate the EscaperRuntime) */
                return $this->escaperRuntime;
            }
            $this->escaperRuntime = new CachedEscaperRuntime(new EscaperRuntime($this->getCharset()));

            /** @phpstan-ignore return.type (There is no other way to decorate the EscaperRuntime) */
            return $this->escaperRuntime;
        }

        return parent::getRuntime($class);
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
