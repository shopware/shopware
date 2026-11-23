<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Config;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomEntity\CustomEntityException;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\AdminUiXmlSchema;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\AdminUiXmlSchemaValidator;
use Shopware\Core\System\CustomEntity\Xml\CustomEntityXmlSchema;

/**
 * @internal
 */
#[Package('framework')]
class CustomEntityEnrichmentService
{
    public function __construct(private readonly AdminUiXmlSchemaValidator $adminUiXmlSchemaValidator)
    {
    }

    public function enrich(
        CustomEntityXmlSchema $customEntityXmlSchema,
        ?AdminUiXmlSchema $adminUiXmlSchema
    ): CustomEntityXmlSchema {
        if ($adminUiXmlSchema !== null) {
            $customEntityXmlSchema = $this->enrichAdminUi($customEntityXmlSchema, $adminUiXmlSchema);
        }

        return $customEntityXmlSchema;
    }

    private function enrichAdminUi(CustomEntityXmlSchema $customEntityXmlSchema, AdminUiXmlSchema $adminUiXmlSchema): CustomEntityXmlSchema
    {
        $adminUiEntitiesConfig = $adminUiXmlSchema->getAdminUi()->getEntities();
        if ($adminUiEntitiesConfig === []) {
            return $customEntityXmlSchema;
        }

        foreach ($customEntityXmlSchema->getEntities()?->getEntities() ?? [] as $entity) {
            if (!\array_key_exists($entity->getName(), $adminUiEntitiesConfig)) {
                continue;
            }

            $this->adminUiXmlSchemaValidator->validateConfigurations(
                $adminUiEntitiesConfig[$entity->getName()],
                $entity
            );

            $flags = [...$entity->getFlags(), ...['admin-ui' => $adminUiEntitiesConfig[$entity->getName()]]];
            $entity->setFlags($flags);

            unset($adminUiEntitiesConfig[$entity->getName()]);
        }

        if ($adminUiEntitiesConfig !== []) {
            throw CustomEntityException::entityNotGiven(
                AdminUiXmlSchema::FILENAME,
                array_keys($adminUiEntitiesConfig)
            );
        }

        return $customEntityXmlSchema;
    }
}
