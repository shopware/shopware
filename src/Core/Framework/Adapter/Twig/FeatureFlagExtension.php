<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\FeatureFlag\FeatureConfig;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class FeatureFlagExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('feature', [$this, 'feature']),
        ];
    }

    public function feature(string $flag): bool
    {
        return FeatureConfig::isActive($flag);
    }
}
