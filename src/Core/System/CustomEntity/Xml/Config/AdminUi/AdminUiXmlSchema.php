<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Config\AdminUi;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomEntity\CustomEntityException;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\AdminUi;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal
 */
#[Package('content')]
final class AdminUiXmlSchema
{
    public const FILENAME = 'admin-ui.xml';

    public const XSD_FILEPATH = __DIR__ . '/admin-ui-1.0.xsd';

    private function __construct(
        private readonly AdminUi $adminUi
    ) {
    }

    public static function createFromXmlFile(string $xmlFilePath): self
    {
        try {
            $doc = XmlUtils::loadFile($xmlFilePath, self::XSD_FILEPATH);
        } catch (\Exception $e) {
            throw CustomEntityException::xmlParsingException($xmlFilePath, $e->getMessage());
        }

        /** @var \DOMElement $domItem */
        $domItem = $doc->getElementsByTagName('admin-ui')->item(0);

        return new self(AdminUi::fromXml($domItem));
    }

    public function getAdminUi(): AdminUi
    {
        return $this->adminUi;
    }
}
