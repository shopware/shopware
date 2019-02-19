<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Twig;

use Shopware\Core\Framework\Twig\TokenParser\ExtendsTokenParser;
use Shopware\Core\Framework\Twig\TokenParser\IncludeTokenParser;
use Twig_Extension;

class InheritanceExtension extends Twig_Extension
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
