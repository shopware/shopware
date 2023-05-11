<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Cms\CmsExtensions;
use Shopware\Core\Framework\App\FlowAction\FlowAction;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomEntity\Xml\CustomEntityXmlSchema;

/**
 * @internal
 */
#[Package('core')]
abstract class AbstractAppLoader
{
    abstract public function getDecorated(): AbstractAppLoader;

    /**
     * @return Manifest[]
     */
    abstract public function load(): array;

    /**
     * @return array<mixed>|null
     */
    abstract public function getConfiguration(AppEntity $app): ?array;

    abstract public function deleteApp(string $technicalName): void;

    abstract public function getCmsExtensions(AppEntity $app): ?CmsExtensions;

    abstract public function getAssetPathForAppPath(string $appPath): string;

    abstract public function getEntities(AppEntity $app): ?CustomEntityXmlSchema;

    abstract public function getFlowActions(AppEntity $app): ?FlowAction;

    /**
     * @return array<string, string>
     */
    abstract public function getSnippets(AppEntity $app): array;

    abstract public function loadFile(string $rootPath, string $filePath): ?string;
}
