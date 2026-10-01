<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Mcp\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class McpTools extends XmlElement
{
    /**
     * @var list<McpTool>
     */
    protected array $tools;

    /**
     * @return list<McpTool>
     */
    public function getTools(): array
    {
        return $this->tools;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function parse(\DOMElement $element): array
    {
        $tools = [];
        foreach ($element->getElementsByTagName('mcp-tool') as $tool) {
            $tools[] = McpTool::fromXml($tool);
        }

        return ['tools' => $tools];
    }
}
