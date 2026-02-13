<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Config\AdminUi;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomEntity\CustomEntityException;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\CardField;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Column;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Detail;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Entity as AdminUiEntity;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Listing;
use Shopware\Core\System\CustomEntity\Xml\Entity;
use Shopware\Core\System\CustomEntity\Xml\Field\Field;

/**
 * @internal
 */
#[Package('framework')]
class AdminUiXmlSchemaValidator
{
    public function validateConfigurations(AdminUiEntity $adminUiEntity, Entity $entity): void
    {
        $entityFields = \array_map(
            static fn (Field $arr): string => $arr->getName(),
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
     * @param list<string> $entityFields
     */
    private function validateListingConfiguration(
        array $entityFields,
        Listing $listing,
        string $customEntityName
    ): void {
        $this->checkReferences(
            $entityFields,
            $this->getRefsAsList($listing->getColumns()->getContent()),
            $customEntityName,
            '<listing>'
        );
    }

    /**
     * @param list<string> $entityFields
     */
    private function validateDetailConfiguration(
        array $entityFields,
        Detail $detail,
        string $customEntityName
    ): void {
        foreach ($detail->getTabs()->getContent() as $tab) {
            foreach ($tab->getCards() as $card) {
                $this->checkReferences(
                    $entityFields,
                    $this->getRefsAsList($card->getFields()),
                    $customEntityName,
                    '<detail>'
                );
            }
        }
    }

    /**
     * @param list<string> $entityFields
     * @param list<string> $referencedFields
     */
    private function checkReferences(
        array $entityFields,
        array $referencedFields,
        string $customEntityName,
        string $xmlElement
    ): void {
        if (\count($referencedFields) !== \count(\array_unique($referencedFields))) {
            throw CustomEntityException::duplicateReferences(
                AdminUiXmlSchema::FILENAME,
                $customEntityName,
                $xmlElement,
                $this->getDuplicates($referencedFields)
            );
        }

        $invalidFields = array_values(array_diff($referencedFields, $entityFields));
        if ($invalidFields !== []) {
            throw CustomEntityException::invalidReferences(
                AdminUiXmlSchema::FILENAME,
                $customEntityName,
                $xmlElement,
                $invalidFields
            );
        }
    }

    /**
     * @param list<string> $entries
     *
     * @return list<string>
     */
    private function getDuplicates(array $entries): array
    {
        return array_values(array_unique(array_diff_assoc($entries, array_unique($entries))));
    }

    /**
     * @param list<Column|CardField> $listOfObjectsWithRefProperty
     *
     * @return list<string>
     */
    private function getRefsAsList(array $listOfObjectsWithRefProperty): array
    {
        return \array_map(
            static fn (Column|CardField $object): string => $object->getRef(),
            $listOfObjectsWithRefProperty
        );
    }
}
