<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Twig;

use Shopware\Core\Framework\FeatureFlag\FeatureConfig;
use Twig_Function;

class FeatureFlagExtension extends \Twig_Extension
{
    public function getFunctions()
    {
        return [
            new Twig_Function('feature', [$this, 'feature']),
        ];
    }

    public function feature($flag)
    {
        return FeatureConfig::isActive($flag);
    }
}
