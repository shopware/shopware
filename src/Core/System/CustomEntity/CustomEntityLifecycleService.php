<?php
declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\System\CustomEntity\Schema\CustomEntityPersister;
use Shopware\Core\System\CustomEntity\Schema\CustomEntitySchemaUpdater;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\AdminUiXmlSchema;
use Shopware\Core\System\CustomEntity\Xml\Config\CustomEntityEnrichmentService;
use Shopware\Core\System\CustomEntity\Xml\CustomEntityXmlSchema;
use Shopware\Core\System\CustomEntity\Xml\CustomEntityXmlSchemaValidator;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
#[Package('core')]
class CustomEntityLifecycleService
{
    public function __construct(
        private readonly CustomEntityPersister $customEntityPersister,
        private readonly CustomEntitySchemaUpdater $customEntitySchemaUpdater,
        private readonly CustomEntityEnrichmentService $customEntityEnrichmentService,
        private readonly CustomEntityXmlSchemaValidator $customEntityXmlSchemaValidator,
        private readonly string $projectDir,
        private readonly SourceResolver $sourceResolver
    ) {
    }

    /**
     * @deprecated tag:v6.7.0 - Custom entity for plugins are deprecated for performance reasons, use attribute entities instead
     */
    public function updatePlugin(string $pluginId, string $pluginPath): ?CustomEntityXmlSchema
    {
        $pathToCustomEntityFile = \sprintf(
            '%s/%s/src/Resources/',
            $this->projectDir,
            $pluginPath,
        );

        if (\file_exists(Path::join($pathToCustomEntityFile, CustomEntityXmlSchema::FILENAME))) {
            Feature::triggerDeprecationOrThrow('v6.7.0.0', 'Custom entity for plugins are deprecated for performance reasons, use attribute entities instead');
        }

        return $this->update(
            $pathToCustomEntityFile,
            PluginEntity::class,
            $pluginId
        );
    }

    public function updateApp(AppEntity $app): ?CustomEntityXmlSchema
    {
        $fs = $this->sourceResolver->filesystemForApp($app);

        if (!$fs->has('Resources')) {
            return null;
        }

        return $this->update(
            $fs->path('Resources'),
            AppEntity::class,
            $app->getId()
        );
    }

    private function update(string $pathToCustomEntityFile, string $extensionEntityType, string $extensionId): ?CustomEntityXmlSchema
    {
        $customEntityXmlSchema = $this->getXmlSchema($pathToCustomEntityFile);
        if ($customEntityXmlSchema === null) {
            return null;
        }

        $customEntityXmlSchema = $this->customEntityEnrichmentService->enrich(
            $customEntityXmlSchema,
            $this->getAdminUiXmlSchema($pathToCustomEntityFile),
        );

        $this->customEntityPersister->update($customEntityXmlSchema->toStorage(), $extensionEntityType, $extensionId);
        $this->customEntitySchemaUpdater->update();

        return $customEntityXmlSchema;
    }

    private function getXmlSchema(string $pathToCustomEntityFile): ?CustomEntityXmlSchema
    {
        $filePath = Path::join($pathToCustomEntityFile, CustomEntityXmlSchema::FILENAME);
        if (!file_exists($filePath)) {
            return null;
        }

        $customEntityXmlSchema = CustomEntityXmlSchema::createFromXmlFile($filePath);
        $this->customEntityXmlSchemaValidator->validate($customEntityXmlSchema);

        return $customEntityXmlSchema;
    }

    private function getAdminUiXmlSchema(string $pathToCustomEntityFile): ?AdminUiXmlSchema
    {
        $configPath = Path::join($pathToCustomEntityFile, 'config', AdminUiXmlSchema::FILENAME);

        if (!file_exists($configPath)) {
            return null;
        }

        return AdminUiXmlSchema::createFromXmlFile($configPath);
    }
}
