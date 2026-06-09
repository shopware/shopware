<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Extension;

use Shopware\Core\Framework\Adapter\Twig\NodeVisitor\FeatureCallOptimizerNodeVisitor;
use Shopware\Core\Framework\Adapter\Twig\TokenParser\FeatureFlagCallTokenParser;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Twig\Extension\AbstractExtension;
use Twig\NodeVisitor\NodeVisitorInterface;
use Twig\TwigFunction;

/**
 * @deprecated tag:v6.8.0 - reason:becomes-internal - Will be internal in v6.8.0
 */
#[Package('framework')]
class FeatureFlagExtension extends AbstractExtension
{
    private const TWIG_COMPILE_TIME_OPTIMIZATION = 'TWIG_COMPILE_TIME_OPTIMIZATION';

    /**
     * @return FeatureFlagCallTokenParser[]
     */
    public function getTokenParsers()
    {
        return [
            new FeatureFlagCallTokenParser(),
        ];
    }

    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('feature', $this->feature(...)),
            new TwigFunction('getAllFeatures', $this->getAll(...)),
        ];
    }

    /**
     * @return NodeVisitorInterface[]
     */
    public function getNodeVisitors(): array
    {
        if (!Feature::isActive(self::TWIG_COMPILE_TIME_OPTIMIZATION)) {
            return [];
        }

        return [
            new FeatureCallOptimizerNodeVisitor(),
        ];
    }

    public function feature(string $flag): bool
    {
        if (!Feature::has($flag)) {
            return false;
        }

        return Feature::isActive($flag);
    }

    /**
     * @return array<string, bool>
     */
    public function getAll(): array
    {
        return Feature::getAll();
    }
}
