<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Extension;

use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * @internal
 */
#[Package('framework')]
class CompatTwigExtension extends AbstractExtension
{
    /**
     * @param array<string, list<string>> $functionsByFeature
     */
    public function __construct(private readonly array $functionsByFeature)
    {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        $functions = [];

        foreach ($this->functionsByFeature as $flag => $names) {
            if (!Feature::isActive($flag)) {
                continue;
            }

            foreach ($names as $name) {
                $functions[] = new TwigFunction($name, static function () use ($flag, $name): never {
                    throw AdapterException::invalidArgument(\sprintf('Twig function "%s" was removed with feature "%s".', $name, $flag));
                });
            }
        }

        return $functions;
    }
}
