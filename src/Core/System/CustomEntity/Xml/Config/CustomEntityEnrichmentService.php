<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Config;

use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\AdminUiXmlSchema;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\AdminUiXmlSchemaValidator;
use Shopware\Core\System\CustomEntity\Xml\Config\CmsAware\CmsAwareXmlSchema;
use Shopware\Core\System\CustomEntity\Xml\CustomEntityXmlSchema;

/**
 * @internal
 */
class CustomEntityEnrichmentService
{
    private AdminUiXmlSchemaValidator $adminUiXmlSchemaValidator;

    public function __construct(
        AdminUiXmlSchemaValidator $adminUiXmlSchemaValidator
    ) {
        $this->adminUiXmlSchemaValidator = $adminUiXmlSchemaValidator;
    }

    public function enrichCmsAwareEntities(?CmsAwareXmlSchema $cmsAwareXmlSchema, CustomEntityXmlSchema $entities): CustomEntityXmlSchema
    {
        if (!$cmsAwareXmlSchema || $entities->getEntities() === null) {
            return $entities;
        }

        if (!($cmsAware = $cmsAwareXmlSchema->getCmsAware())) {
            return $entities;
        }

        $cmsAwareEntitiesConfig = $cmsAware->getEntities();

        foreach ($entities->getEntities()->getEntities() as $entity) {
            if (!\array_key_exists($entity->getName(), $cmsAwareEntitiesConfig)) {
                continue;
            }

            $fields = $entity->getFields();
            $fields = array_merge($fields, CmsAwareXmlSchema::getCmsAwareFields());
            $entity->setFields($fields);

            $flags = $entity->getFlags();
            $flags = array_merge($flags, ['cms-aware' => $cmsAwareEntitiesConfig[$entity->getName()]]);
            $entity->setFlags($flags);

            unset($cmsAwareEntitiesConfig[$entity->getName()]);
        }

        if (!empty($cmsAwareEntitiesConfig)) {
            throw CustomEntityConfigurationException::entityNotGiven(CmsAwareXmlSchema::FILENAME, array_keys($cmsAwareEntitiesConfig));
        }

        return $entities;
    }

    public function enrichAdminUiEntities(?AdminUiXmlSchema $adminUiXmlSchema, CustomEntityXmlSchema $customEntityXmlSchema): CustomEntityXmlSchema
    {
        if (!$adminUiXmlSchema || $customEntityXmlSchema->getEntities() === null) {
            return $customEntityXmlSchema;
        }

        if (!($adminUi = $adminUiXmlSchema->getAdminUi())) {
            return $customEntityXmlSchema;
        }

        $adminUiEntitiesConfig = $adminUi->getEntities();

        foreach ($customEntityXmlSchema->getEntities()->getEntities() as $entity) {
            if (!\array_key_exists($entity->getName(), $adminUiEntitiesConfig)) {
                continue;
            }

            $this->adminUiXmlSchemaValidator->validateConfigurations(
                $adminUiEntitiesConfig[$entity->getName()],
                $entity
            );

            $flags = $entity->getFlags();
            $flags = array_merge($flags, ['admin-ui' => $adminUiEntitiesConfig[$entity->getName()]]);
            $entity->setFlags($flags);

            unset($adminUiEntitiesConfig[$entity->getName()]);
        }

        if (!empty($adminUiEntitiesConfig)) {
            throw CustomEntityConfigurationException::entityNotGiven(AdminUiXmlSchema::FILENAME, array_keys($adminUiEntitiesConfig));
        }

        return $customEntityXmlSchema;
    }
}
