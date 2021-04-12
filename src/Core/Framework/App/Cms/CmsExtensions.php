<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Cms;

use Shopware\Core\Framework\App\Cms\Xml\Blocks;
use Shopware\Core\System\SystemConfig\Exception\XmlParsingException;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal (flag:FEATURE_NEXT_14408)
 */
class CmsExtensions
{
    private const XSD_FILE = __DIR__ . '/Schema/cms-1.0.xsd';

    private string $path;

    private ?Blocks $blocks;

    private function __construct(string $path, ?Blocks $blocks)
    {
        $this->path = $path;
        $this->blocks = $blocks;
    }

    public static function createFromXmlFile(string $xmlFile): self
    {
        try {
            $doc = XmlUtils::loadFile($xmlFile, self::XSD_FILE);
        } catch (\Exception $e) {
            throw new XmlParsingException($xmlFile, $e->getMessage());
        }

        $blocks = $doc->getElementsByTagName('blocks')->item(0);
        $blocks = $blocks === null ? null : Blocks::fromXml($blocks);

        return new self(\dirname($xmlFile), $blocks);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getBlocks(): ?Blocks
    {
        return $this->blocks;
    }
}
