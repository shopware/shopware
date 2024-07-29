<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Cms\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('buyers-experience')]
class Blocks extends XmlElement
{
    /**
     * @var list<Block>
     */
    protected array $blocks = [];

    /**
     * @return list<Block>
     */
    public function getBlocks(): array
    {
        return $this->blocks;
    }

    protected static function parse(\DOMElement $element): array
    {
        $blocks = [];

        foreach ($element->getElementsByTagName('block') as $block) {
            $blocks[] = Block::fromXml($block);
        }

        return ['blocks' => $blocks];
    }
}
