<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Extension;

use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Adapter\Twig\TokenParser\ExtendsTokenParser;
use Shopware\Core\Framework\Adapter\Twig\TokenParser\IncludeTokenParser;
use Shopware\Core\Framework\Adapter\Twig\TokenParser\ReturnNodeTokenParser;
use Shopware\Core\Framework\Log\Package;
use Twig\Extension\AbstractExtension;

#[Package('core')]
class NodeExtension extends AbstractExtension
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
            new ExtendsTokenParser($this->finder),
            new IncludeTokenParser($this->finder),
            new ReturnNodeTokenParser(),
        ];
    }

    public function getFinder(): TemplateFinder
    {
        return $this->finder;
    }
}
