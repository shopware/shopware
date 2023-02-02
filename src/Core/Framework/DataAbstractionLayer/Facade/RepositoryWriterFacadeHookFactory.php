<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Facade;

use Shopware\Core\Framework\Api\Sync\SyncService;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;

/**
 * @internal
 */
class RepositoryWriterFacadeHookFactory extends HookServiceFactory
{
    private DefinitionInstanceRegistry $registry;

    private AppContextCreator $appContextCreator;

    private SyncService $syncService;

    public function __construct(
        DefinitionInstanceRegistry $registry,
        AppContextCreator $appContextCreator,
        SyncService $syncService
    ) {
        $this->registry = $registry;
        $this->appContextCreator = $appContextCreator;
        $this->syncService = $syncService;
    }

    public function factory(Hook $hook, Script $script): RepositoryWriterFacade
    {
        return new RepositoryWriterFacade(
            $this->registry,
            $this->syncService,
            $this->appContextCreator->getAppContext($hook, $script)
        );
    }

    public function getName(): string
    {
        return 'writer';
    }
}
