<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Mcp;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Mcp\Xml\McpTools;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class Mcp
{
    private function __construct(
        private string $path,
        private readonly ?McpTools $tools,
    ) {
    }

    public static function createFromXmlFile(string $xmlFile): self
    {
        if (!is_readable($xmlFile)) {
            throw AppException::xmlParsingException($xmlFile, \sprintf('File "%s" is not readable or does not exist.', $xmlFile));
        }

        try {
            $doc = new \DOMDocument();
            $doc->loadXML((string) file_get_contents($xmlFile));
        } catch (\Exception $e) {
            throw AppException::xmlParsingException($xmlFile, $e->getMessage());
        }

        $toolsElement = $doc->getElementsByTagName('mcp-tools')->item(0);
        $tools = $toolsElement instanceof \DOMElement ? McpTools::fromXml($toolsElement) : null;

        return new self(\dirname($xmlFile), $tools);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getTools(): ?McpTools
    {
        return $this->tools;
    }
}
