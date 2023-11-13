<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig;

use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Twig\TokenParser\IconTokenParser;
use Twig\Extension\AbstractExtension;

#[Package('storefront')]
class IconExtension extends AbstractExtension
{
    /**
     * @internal
     */
    public function __construct(private readonly TemplateFinder $finder)
    {
    }

    public function getTokenParsers(): array
    {
        return [
            new IconTokenParser(),
        ];
    }

    /**
     * @deprecated tag:v6.6.0 - Will be removed, use constructor injection instead
     */
    public function getFinder(): TemplateFinder
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0')
        );

        return $this->finder;
    }
}
