<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Cms\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('content')]
class Blocks extends XmlElement
{
    /**
     * @param Block[] $blocks
     */
    private function __construct(protected array $blocks)
    {
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
