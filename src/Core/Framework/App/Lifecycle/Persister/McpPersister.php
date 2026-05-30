<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleContext;
use Shopware\Core\Framework\App\Mcp\Mcp;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class McpPersister implements PersisterInterface
{
    public function __construct(
        private readonly McpToolPersister $toolPersister,
        private readonly McpPromptPersister $promptPersister,
        private readonly McpResourcePersister $resourcePersister,
    ) {
    }

    public function persist(AppLifecycleContext $context): void
    {
        $mcp = $this->getMcp($context);

        $this->toolPersister->validateRequiredPrivileges($context->manifest, $mcp);
        $this->toolPersister->persist($mcp, $context->app->getId(), $context->defaultLocale, $context->context);
        $this->promptPersister->persist($mcp, $context->app->getId(), $context->defaultLocale, $context->context);
        $this->resourcePersister->persist($mcp, $context->app->getId(), $context->defaultLocale, $context->context);
    }

    public function activate(AppEntity $app, Context $context): void
    {
    }

    public function deactivate(AppEntity $app, Context $context): void
    {
    }

    private function getMcp(AppLifecycleContext $context): ?Mcp
    {
        if (!$context->appFilesystem->has('Resources/mcp.xml')) {
            return null;
        }

        return Mcp::createFromXmlFile($context->appFilesystem->path('Resources/mcp.xml'));
    }
}
