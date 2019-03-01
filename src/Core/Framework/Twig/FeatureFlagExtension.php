<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Twig;

use Shopware\Core\Framework\FeatureFlag\FeatureConfig;

class FeatureFlagExtension extends \Twig_Extension
{
    public function getFunctions(): array
    {
        return [
            new \Twig_Function('feature', [$this, 'feature']),
        ];
    }

    public function feature(string $flag): bool
    {
        return FeatureConfig::isActive($flag);
    }
}
