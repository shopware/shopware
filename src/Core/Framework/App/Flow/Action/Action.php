<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Flow\Action;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Flow\Action\Xml\Actions;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Config\Util\XmlUtils;

#[Package('core')]
class Action
{
    private const XSD_FLOW_FILE = '/Schema/flow-1.0.xsd';

    private function __construct(
        private string $path,
        private readonly ?Actions $actions
    ) {
    }

    public static function createFromXmlFile(string $xmlFile): self
    {
        $schemaFile = \dirname(__FILE__, 2) . self::XSD_FLOW_FILE;

        try {
            $doc = XmlUtils::loadFile($xmlFile, $schemaFile);
        } catch (\Exception $e) {
            throw AppException::createFromXmlFileFlowError($xmlFile, $e->getMessage(), $e);
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
