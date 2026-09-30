<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\MyFakeNamespace;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * @deprecated tag:v6.9.0 - Will be removed
 */
class WrongVersionTwigExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [new TwigFunction('category_url', static fn () => null)];
    }
}
