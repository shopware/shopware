<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\ScriptFileReaderInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\ScriptCollection;
use Shopware\Core\Framework\Script\ScriptEntity;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class ScriptPersister
{
    public function __construct(
        private readonly ScriptFileReaderInterface $scriptReader,
        private readonly EntityRepository $scriptRepository,
        private readonly EntityRepository $appRepository
    ) {
    }

    public function updateScripts(string $appId, Context $context): void
    {
        $app = $this->getAppWithExistingScripts($appId, $context);

        /** @var ScriptCollection $existingScripts */
        $existingScripts = $app->getScripts();

        $scriptPaths = $this->scriptReader->getScriptPathsForApp($app->getPath());

        $upserts = [];
        foreach ($scriptPaths as $scriptPath) {
            $payload = [
                'script' => $this->scriptReader->getScriptContent($scriptPath, $app->getPath()),
            ];

            /** @var ScriptEntity|null $existing */
            $existing = $existingScripts->filterByProperty('name', $scriptPath)->first();
            if ($existing) {
                $existingScripts->remove($existing->getId());

                if ($existing->getScript() === $payload['script']) {
                    // Don't update DB when content is identical
                    continue;
                }
                $payload['id'] = $existing->getId();
            } else {
                $payload['appId'] = $appId;
                $payload['active'] = $app->isActive();
                $payload['name'] = $scriptPath;
                $payload['hook'] = explode('/', $scriptPath)[0];
            }

            $upserts[] = $payload;
        }

        if (!empty($upserts)) {
            $this->scriptRepository->upsert($upserts, $context);
        }

        $this->deleteOldScripts($existingScripts, $context);
    }

    public function activateAppScripts(string $appId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->setTitle('app-scripts::activate');
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $criteria->addFilter(new EqualsFilter('active', false));

        /** @var array<string> $scripts */
        $scripts = $this->scriptRepository->searchIds($criteria, $context)->getIds();

        $updateSet = array_map(fn (string $id) => ['id' => $id, 'active' => true], $scripts);

        $this->scriptRepository->update($updateSet, $context);
    }

    public function deactivateAppScripts(string $appId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->setTitle('app-scripts::deactivate');
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $criteria->addFilter(new EqualsFilter('active', true));

        /** @var array<string> $scripts */
        $scripts = $this->scriptRepository->searchIds($criteria, $context)->getIds();

        $updateSet = array_map(fn (string $id) => ['id' => $id, 'active' => false], $scripts);

        $this->scriptRepository->update($updateSet, $context);
    }

    public function refresh(): void
    {
        $criteria = new Criteria();
        $criteria->setTitle('app-scripts::refresh');
        $criteria->addFilter(new EqualsFilter('active', true));

        $apps = $this->appRepository->search($criteria, Context::createDefaultContext())->getEntities();

        /** @var AppEntity $app */
        foreach ($apps as $app) {
            $this->updateScripts($app->getId(), Context::createDefaultContext());
        }
    }

    private function deleteOldScripts(ScriptCollection $toBeRemoved, Context $context): void
    {
        /** @var array<string> $ids */
        $ids = $toBeRemoved->getIds();

        if (!empty($ids)) {
            $ids = array_map(static fn (string $id): array => ['id' => $id], array_values($ids));

            $this->scriptRepository->delete($ids, $context);
        }
    }

    private function getAppWithExistingScripts(string $appId, Context $context): AppEntity
    {
        $criteria = new Criteria([$appId]);
        $criteria->addAssociation('scripts');

        /** @var AppEntity $app */
        $app = $this->appRepository->search($criteria, $context)->first();

        return $app;
    }
}
