<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Flow\Event;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Flow\Event\Xml\CustomEvents;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal
 */
#[Package('core')]
class Event
{
    private const XSD_FILE = '/Schema/flow-1.0.xsd';

    private function __construct(
        private string $path,
        private readonly ?CustomEvents $customEvents
    ) {
    }

    public static function createFromXmlFile(string $xmlFile): self
    {
        try {
            $schemaFile = \dirname(__FILE__, 2) . self::XSD_FILE;
            $doc = XmlUtils::loadFile($xmlFile, $schemaFile);
        } catch (\Exception $e) {
            throw AppException::createFromXmlFileFlowError($xmlFile, $e->getMessage(), $e);
        }

        $customEvents = $doc->getElementsByTagName('flow-events')->item(0);
        $customEvents = $customEvents === null ? null : CustomEvents::fromXml($customEvents);

        return new self(\dirname($xmlFile), $customEvents);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getCustomEvents(): ?CustomEvents
    {
        return $this->customEvents;
    }
}
