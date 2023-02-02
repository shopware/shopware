<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\FlowAction;

use Shopware\Core\Framework\App\FlowAction\Xml\Actions;
use Shopware\Core\System\SystemConfig\Exception\XmlParsingException;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal
 */
class FlowAction
{
    private const XSD_FILE = __DIR__ . '/Schema/flow-action-1.0.xsd';

    private string $path;

    private ?Actions $actions;

    private function __construct(string $path, ?Actions $actions)
    {
        $this->path = $path;
        $this->actions = $actions;
    }

    public static function createFromXmlFile(string $xmlFile): self
    {
        try {
            $doc = XmlUtils::loadFile($xmlFile, self::XSD_FILE);
        } catch (\Exception $e) {
            throw new XmlParsingException($xmlFile, $e->getMessage());
        }

        $actions = $doc->getElementsByTagName('flow-actions')->item(0);
        $actions = $actions === null ? null : Actions::fromXml($actions);

        return new self(\dirname($xmlFile), $actions);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getActions(): ?Actions
    {
        return $this->actions;
    }
}
