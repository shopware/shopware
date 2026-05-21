<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\Persister\McpPromptPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\McpResourcePersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\McpToolPersister;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Mcp\Mcp;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class McpAppSyncer
{
    public function __construct(
        private readonly McpToolPersister $toolPersister,
        private readonly McpPromptPersister $promptPersister,
        private readonly McpResourcePersister $resourcePersister,
        private readonly SourceResolver $sourceResolver,
    ) {
    }

    public function sync(Manifest $manifest, AppEntity $app, string $defaultLocale, Context $context): void
    {
        $mcp = $this->getMcp($app);
        $this->toolPersister->validateRequiredPrivileges($manifest, $mcp);
        $this->toolPersister->persist($mcp, $app->getId(), $defaultLocale, $context);
        $this->promptPersister->persist($mcp, $app->getId(), $defaultLocale, $context);
        $this->resourcePersister->persist($mcp, $app->getId(), $defaultLocale, $context);
    }

    private function getMcp(AppEntity $app): ?Mcp
    {
        $fs = $this->sourceResolver->filesystemForApp($app);

        if (!$fs->has('Resources/mcp.xml')) {
            return null;
        }

        return Mcp::createFromXmlFile($fs->path('Resources/mcp.xml'));
    }
}
