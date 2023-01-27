<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\Exception\XmlParsingException;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal
 */
#[Package('core')]
class CustomEntityXmlSchema
{
    final public const FILENAME = 'entities.xml';

    private const XSD_FILE = __DIR__ . '/entity-1.0.xsd';

    public function __construct(
        private string $path,
        private readonly ?Entities $entities
    ) {
    }

    public static function createFromXmlFile(string $xmlFile): self
    {
        try {
            $doc = XmlUtils::loadFile($xmlFile, self::XSD_FILE);
        } catch (\Exception $e) {
            throw new XmlParsingException($xmlFile, $e->getMessage());
        }

        $entities = $doc->getElementsByTagName('entities')->item(0);
        $entities = $entities === null ? null : Entities::fromXml($entities);

        return new self(\dirname($xmlFile), $entities);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getEntities(): ?Entities
    {
        return $this->entities;
    }

    /**
     * @return array<string, mixed>
     */
    public function toStorage(): array
    {
        if ($this->entities === null) {
            return [];
        }

        return json_decode(json_encode($this->entities->getEntities(), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);
    }
}
