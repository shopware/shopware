<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\MyFakeNamespace;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * @deprecated tag:v6.8.0 - Will be removed
 */
class CoveredTwigExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [new TwigFunction('category_url', static fn () => null)];
    }
}
