<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Config;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\AdminUiXmlSchema;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\AdminUiXmlSchemaValidator;
use Shopware\Core\System\CustomEntity\Xml\Config\CmsAware\CmsAwareFields;
use Shopware\Core\System\CustomEntity\Xml\CustomEntityXmlSchema;

/**
 * @internal
 */
#[Package('content')]
class CustomEntityEnrichmentService
{
    public function __construct(private readonly AdminUiXmlSchemaValidator $adminUiXmlSchemaValidator)
    {
    }

    public function enrich(
        CustomEntityXmlSchema $customEntityXmlSchema,
        ?AdminUiXmlSchema $adminUiXmlSchema
    ): CustomEntityXmlSchema {
        // @todo NEXT-22697 - Re-implement, when re-enabling cms-aware
        //$customEntityXmlSchema = $this->enrichCmsAware($customEntityXmlSchema);

        if ($adminUiXmlSchema !== null) {
            $customEntityXmlSchema = $this->enrichAdminUi($customEntityXmlSchema, $adminUiXmlSchema);
        }

        return $customEntityXmlSchema;
    }

    private function enrichCmsAware(CustomEntityXmlSchema $customEntityXmlSchema): CustomEntityXmlSchema
    {
        foreach ($customEntityXmlSchema->getEntities()?->getEntities() ?? [] as $entity) {
            if ($entity->isCmsAware() !== true) {
                continue;
            }

            $fields = $entity->getFields();
            $fields = array_merge($fields, CmsAwareFields::getCmsAwareFields());
            $entity->setFields($fields);

            $flags = $entity->getFlags();
            $flags = [...$flags, ...['cms-aware' => ['name' => $entity->getName()]]];
            $entity->setFlags($flags);
        }

        return $customEntityXmlSchema;
    }

    private function enrichAdminUi(CustomEntityXmlSchema $customEntityXmlSchema, AdminUiXmlSchema $adminUiXmlSchema): CustomEntityXmlSchema
    {
        $adminUiEntitiesConfig = $adminUiXmlSchema->getAdminUi()?->getEntities();
        if ($adminUiEntitiesConfig === null) {
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

        if (!empty($adminUiEntitiesConfig)) {
            throw CustomEntityConfigurationException::entityNotGiven(
                AdminUiXmlSchema::FILENAME,
                array_keys($adminUiEntitiesConfig)
            );
        }

        return $customEntityXmlSchema;
    }
}
