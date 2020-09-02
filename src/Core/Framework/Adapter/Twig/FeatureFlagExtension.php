<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Feature;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class FeatureFlagExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('feature', [$this, 'feature']),
            new TwigFunction('getAllFeatures', [$this, 'getAll']),
        ];
    }

    public function feature(string $flag): bool
    {
        return Feature::isActive($flag);
    }

    public function getAll(): array
    {
        return Feature::getAll();
    }
}
