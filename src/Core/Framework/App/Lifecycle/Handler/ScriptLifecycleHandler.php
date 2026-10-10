<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Handler;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\ScriptFileReader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\ScriptCollection;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class ScriptLifecycleHandler extends AbstractLifecycleHandler
{
    /**
     * @param EntityRepository<ScriptCollection> $scriptRepository
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly ScriptFileReader $scriptReader,
        private readonly EntityRepository $scriptRepository,
        private readonly EntityRepository $appRepository
    ) {
    }

    public function install(AppPersistContext $context): void
    {
        $this->updateScripts($context->app->getId(), $context->context);
    }

    public function update(AppPersistContext $context): void
    {
        $this->updateScripts($context->app->getId(), $context->context);
    }

    public function activate(AppActivationContext $context): void
    {
        $criteria = new Criteria();
        $criteria->setTitle('app-scripts::activate');
        $criteria->addFilter(new EqualsFilter('appId', $context->app->getId()));
        $criteria->addFilter(new EqualsFilter('active', false));

        $scripts = $this->scriptRepository->searchIds($criteria, $context->context)->getPrimaryKeyData();
        foreach ($scripts as &$script) {
            $script['active'] = true;
        }
        unset($script);

        $this->scriptRepository->update($scripts, $context->context);
    }

    public function deactivate(AppActivationContext $context): void
    {
        $criteria = new Criteria();
        $criteria->setTitle('app-scripts::deactivate');
        $criteria->addFilter(new EqualsFilter('appId', $context->app->getId()));
        $criteria->addFilter(new EqualsFilter('active', true));

        $scripts = $this->scriptRepository->searchIds($criteria, $context->context)->getPrimaryKeyData();
        foreach ($scripts as &$script) {
            $script['active'] = false;
        }
        unset($script);

        $this->scriptRepository->update($scripts, $context->context);
    }

    /**
     * Refresh is only called on dev to update scripts independently of the app lifecycle for better dev experience
     */
    public function refresh(): void
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->setTitle('app-scripts::refresh');
        $criteria->addFilter(new EqualsFilter('active', true));

        // We don't automatically update service scripts, as that would do a request to the service on every request to shopware
        $criteria->addFilter(new EqualsFilter('selfManaged', false));

        $appIds = $this->appRepository->searchIds($criteria, $context)->getIds();

        if ($appIds === []) {
            return;
        }

        // the scripts of every app are loaded together instead of one app per iteration
        $criteria = new Criteria($appIds);
        $criteria->addAssociation('scripts');

        $upserts = [];
        $deletes = [];

        foreach ($this->appRepository->search($criteria, $context)->getEntities() as $app) {
            $this->collectScriptChanges($app, $upserts, $deletes);
        }

        $this->writeScriptChanges($upserts, $deletes, $context);
    }

    private function updateScripts(string $appId, Context $context): void
    {
        $upserts = [];
        $deletes = [];

        $this->collectScriptChanges($this->getAppWithExistingScripts($appId, $context), $upserts, $deletes);

        $this->writeScriptChanges($upserts, $deletes, $context);
    }

    /**
     * @param list<array<string, mixed>> $upserts
     * @param list<array{id: string}> $deletes
     */
    private function collectScriptChanges(AppEntity $app, array &$upserts, array &$deletes): void
    {
        $existingScripts = $app->getScripts();
        \assert($existingScripts !== null);

        $scriptPaths = $this->scriptReader->getScriptPathsForApp($app);

        $upserts = [];
        foreach ($scriptPaths as $scriptPath) {
            $payload = [
                'script' => $this->scriptReader->getScriptContent($app, $scriptPath),
            ];

            $existing = $existingScripts->filterByProperty('name', $scriptPath)->first();
            if ($existing) {
                $existingScripts->remove($existing->getId());

                if ($existing->getScript() === $payload['script']) {
                    // Don't update DB when content is identical
                    continue;
                }
                $payload['id'] = $existing->getId();
            } else {
                $payload['appId'] = $app->getId();
                $payload['active'] = $app->isActive();
                $payload['name'] = $scriptPath;
                $payload['hook'] = explode('/', $scriptPath)[0];
            }

            $upserts[] = $payload;
        }

        foreach (array_values($existingScripts->getIds()) as $id) {
            $deletes[] = ['id' => $id];
        }
    }

    /**
     * @param list<array<string, mixed>> $upserts
     * @param list<array{id: string}> $deletes
     */
    private function writeScriptChanges(array $upserts, array $deletes, Context $context): void
    {
        if ($upserts !== []) {
            $this->scriptRepository->upsert($upserts, $context);
        }

        if ($deletes !== []) {
            $this->scriptRepository->delete($deletes, $context);
        }
    }

    private function getAppWithExistingScripts(string $appId, Context $context): AppEntity
    {
        $criteria = new Criteria([$appId]);
        $criteria->addAssociation('scripts');

        $app = $this->appRepository->search($criteria, $context)->getEntities()->first();
        \assert($app !== null);

        return $app;
    }
}
