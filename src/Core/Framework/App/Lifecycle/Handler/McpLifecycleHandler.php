<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Handler;

use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Persister\McpPromptPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\McpResourcePersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\McpToolPersister;
use Shopware\Core\Framework\App\Mcp\Mcp;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Notification\AppMcpCapabilityDetector;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotifier;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class McpLifecycleHandler extends AbstractLifecycleHandler
{
    public function __construct(
        private readonly McpToolPersister $toolPersister,
        private readonly McpPromptPersister $promptPersister,
        private readonly McpResourcePersister $resourcePersister,
        private readonly ?AppMcpCapabilityDetector $capabilityDetector = null,
        private readonly ?McpListChangedNotifier $listChangedNotifier = null,
    ) {
    }

    public function install(AppPersistContext $context): void
    {
        $this->persist($context);
    }

    public function update(AppPersistContext $context): void
    {
        $this->persist($context);
    }

    private function persist(AppPersistContext $context): void
    {
        $mcp = $this->getMcp($context);
        $existingCapabilities = $this->capabilityDetector?->persistedForApp($context->app->getId());
        $newCapabilities = $this->capabilityDetector?->fromMcp($mcp);

        $this->toolPersister->validateRequiredPrivileges($context->manifest, $mcp);
        $this->toolPersister->persist($mcp, $context->app->getId(), $context->defaultLocale, $context->context);
        $this->promptPersister->persist($mcp, $context->app->getId(), $context->defaultLocale, $context->context);
        $this->resourcePersister->persist($mcp, $context->app->getId(), $context->defaultLocale, $context->context);

        if ($existingCapabilities !== null && $newCapabilities !== null) {
            $this->listChangedNotifier?->notify($existingCapabilities->merge($newCapabilities));
        }
    }

    private function getMcp(AppPersistContext $context): ?Mcp
    {
        if (!$context->appFilesystem->has('Resources/mcp.xml')) {
            return null;
        }

        return Mcp::createFromXmlFile($context->appFilesystem->path('Resources/mcp.xml'));
    }
}
