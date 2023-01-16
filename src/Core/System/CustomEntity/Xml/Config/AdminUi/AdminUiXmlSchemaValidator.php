<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Config\AdminUi;

use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Detail;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Entity as AdminUiEntity;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Listing;
use Shopware\Core\System\CustomEntity\Xml\Config\CustomEntityConfigurationException;
use Shopware\Core\System\CustomEntity\Xml\Entity;

/**
 * @package content
 *
 * @internal
 */
class AdminUiXmlSchemaValidator
{
    public function validateConfigurations(AdminUiEntity $adminUiEntity, Entity $entity): void
    {
        $entityFields = \array_map(
            function ($arr) {
                return $arr->getName();
            },
            $entity->getFields()
        );
        $this->validateListingConfiguration(
            $entityFields,
            $adminUiEntity->getListing(),
            $adminUiEntity->getName()
        );
        $this->validateDetailConfiguration(
            $entityFields,
            $adminUiEntity->getDetail(),
            $adminUiEntity->getName()
        );
    }

    /**
     * @param string[] $entityFields
     */
    private function validateListingConfiguration(
        array $entityFields,
        Listing $listing,
        string $customEntityName
    ): void {
        $this->checkReferences(
            $entityFields,
            array_column($listing->getColumns()->toArray(''), 'ref'),
            $customEntityName,
            '<listing>'
        );
    }

    /**
     * @param string[] $entityFields
     */
    private function validateDetailConfiguration(
        array $entityFields,
        Detail $detail,
        string $customEntityName
    ): void {
        $tabs = $detail->getTabs()->getContent();

        foreach ($tabs as $tab) {
            $cards = $tab->getCards();
            foreach ($cards as $card) {
                $this->checkReferences(
                    $entityFields,
                    array_column($card->getFields(), 'ref'),
                    $customEntityName,
                    '<detail>'
                );
            }
        }
    }

    /**
     * @param string[] $entityFields
     * @param string[] $referencedFields
     */
    private function checkReferences(
        array $entityFields,
        array $referencedFields,
        string $customEntityName,
        string $xmlElement
    ): void {
        if (\count($referencedFields) !== \count(\array_unique($referencedFields))) {
            throw CustomEntityConfigurationException::duplicateReferences(
                AdminUiXmlSchema::FILENAME,
                $customEntityName,
                $xmlElement,
                $this->getDuplicates($referencedFields)
            );
        }

        $invalidFields = array_diff($referencedFields, $entityFields);
        if (!empty($invalidFields)) {
            throw CustomEntityConfigurationException::invalidReferences(
                AdminUiXmlSchema::FILENAME,
                $customEntityName,
                $xmlElement,
                $invalidFields
            );
        }
    }

    /**
     * @param string[] $entries
     *
     * @return string[]
     */
    private function getDuplicates(array $entries): array
    {
        return array_unique(array_diff_assoc($entries, array_unique($entries)));
    }
}
