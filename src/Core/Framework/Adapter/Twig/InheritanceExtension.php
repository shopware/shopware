<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Adapter\Twig\TokenParser\ExtendsTokenParser;
use Shopware\Core\Framework\Adapter\Twig\TokenParser\IncludeTokenParser;
use Twig\Extension\AbstractExtension;

class InheritanceExtension extends AbstractExtension
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
            new ExtendsTokenParser($this->finder),
            new IncludeTokenParser($this->finder),
        ];
    }

    public function getFinder(): TemplateFinder
    {
        return $this->finder;
    }
}
