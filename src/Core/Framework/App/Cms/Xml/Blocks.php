<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Cms\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;

/**
 * @internal (flag:FEATURE_NEXT_14408)
 */
class Blocks extends XmlElement
{
    /**
     * @var Block[]
     */
    protected array $blocks = [];

    private function __construct(array $blocks)
    {
        $this->blocks = $blocks;
    }

    public static function fromXml(\DOMElement $element): self
    {
        return new self(self::parseBlocks($element));
    }

    /**
     * @return Block[]
     */
    public function getBlocks(): array
    {
        return $this->blocks;
    }

    private static function parseBlocks(\DOMElement $element): array
    {
        $blocks = [];

        foreach ($element->getElementsByTagName('block') as $block) {
            $blocks[] = Block::fromXml($block);
        }

        return $blocks;
    }
}
