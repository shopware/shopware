<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Shopware\Core\Framework\App\Aggregate\AppMcpTool\AppMcpToolCollection;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Mcp\Mcp;
use Shopware\Core\Framework\App\Validation\Error\MissingPermissionError;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @extends AbstractMcpCapabilityPersister<AppMcpToolCollection>
 */
#[Package('framework')]
class McpToolPersister extends AbstractMcpCapabilityPersister
{
    /**
     * @param EntityRepository<AppMcpToolCollection> $mcpToolRepository
     */
    public function __construct(
        private readonly EntityRepository $mcpToolRepository,
    ) {
    }

    public function validateRequiredPrivileges(Manifest $manifest, ?Mcp $mcp): void
    {
        $permissions = $manifest->getPermissions();
        if ($permissions === null) {
            return;
        }

        $tools = $mcp?->getTools()?->getTools() ?? [];
        $granted = $permissions->asParsedPrivileges();
        $appName = $manifest->getMetadata()->getName();

        foreach ($tools as $tool) {
            $required = $tool->getRequiredPrivileges();
            if ($required === []) {
                continue;
            }

            $missing = array_values(array_filter(
                $required,
                static fn (string $privilege): bool => !\in_array($privilege, $granted, true),
            ));

            if ($missing === []) {
                continue;
            }

            throw AppException::invalidConfiguration(
                $appName,
                new MissingPermissionError(array_map(
                    static fn (string $p): string => \sprintf('Tool "%s" requires "%s" but it is not declared in <permissions>', $tool->getName(), $p),
                    $missing,
                )),
            );
        }
    }

    protected function getItemsFromMcp(?Mcp $mcp): array
    {
        return $mcp?->getTools()?->getTools() ?? [];
    }

    /**
     * @return EntityRepository<AppMcpToolCollection>
     */
    protected function getRepository(): EntityRepository
    {
        return $this->mcpToolRepository;
    }

    /**
     * @return AppMcpToolCollection
     */
    protected function fetchExisting(string $appId, Context $context): EntityCollection
    {
        return $this->searchByAppId($this->mcpToolRepository, $appId, $context);
    }
}
