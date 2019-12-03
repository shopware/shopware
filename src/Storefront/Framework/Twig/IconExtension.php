<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig;

use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Storefront\Framework\Twig\TokenParser\IconTokenParser;
use Twig\Extension\AbstractExtension;

class IconExtension extends AbstractExtension
{
    /**
     * @var TemplateFinder
     */
    private $finder;

    public function __construct(TemplateFinder $finder)
    {
        $this->finder = $finder;
    }

    public function getTokenParsers(): array
    {
        return [
            new IconTokenParser(),
        ];
    }

    public function getFinder(): TemplateFinder
    {
        return $this->finder;
    }
}
