<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Config\AdminUi;

use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\AdminUi;
use Shopware\Core\System\SystemConfig\Exception\XmlParsingException;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal
 */
class AdminUiXmlSchema
{
    public const FILENAME = 'admin-ui.xml';

    private const XSD_FILE = __DIR__ . '/admin-ui-1.0.xsd';

    private ?AdminUi $adminUi;

    public function __construct(?AdminUi $adminUi)
    {
        $this->adminUi = $adminUi;
    }

    public function getAdminUi(): ?AdminUi
    {
        return $this->adminUi;
    }

    public static function createFromXmlFile(string $xmlFilePath): self
    {
        try {
            $doc = XmlUtils::loadFile($xmlFilePath, self::XSD_FILE);
        } catch (\Exception $e) {
            throw new XmlParsingException($xmlFilePath, $e->getMessage());
        }

        $config = $doc->getElementsByTagName('admin-ui')->item(0);
        $config = $config === null ? null : AdminUi::fromXml($config);

        return new self($config);
    }
}
