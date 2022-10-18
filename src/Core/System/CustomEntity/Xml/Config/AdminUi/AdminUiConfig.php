<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Config\AdminUi;

use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\Entity as AdminUiEntity;
use Shopware\Core\System\CustomEntity\Xml\Entity;
use Shopware\Core\System\SystemConfig\Exception\XmlParsingException;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal
 */
class AdminUiConfig
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

    public static function validateConfiguration(AdminUiEntity $adminUiEntity, Entity $entity): void
    {
        $listing = $adminUiEntity->getListing();
        $referencedFields = array_column($listing->getColumns(), 'ref');
        $referencedFields = array_unique($referencedFields);

        $entityFields = [];
        foreach ($entity->getFields() as $field) {
            $entityFields[] = $field->getName();
        }

        $intersect = array_intersect($referencedFields, $entityFields);
        if (\count($intersect) !== \count($referencedFields)) {
            $invalidFields = array_diff($referencedFields, $intersect);

            //TODO: throw error
        }
    }
}
