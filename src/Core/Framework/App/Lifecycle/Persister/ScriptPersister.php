<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\ScriptFileReaderInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Script\ScriptCollection;
use Shopware\Core\Framework\Script\ScriptEntity;

/**
 * @internal only for use by the app-system
 */
class ScriptPersister
{
    private ScriptFileReaderInterface $scriptReader;

    private EntityRepositoryInterface $scriptRepository;

    private EntityRepositoryInterface $appRepository;

    private string $projectDir;

    public function __construct(
        ScriptFileReaderInterface $scriptReader,
        EntityRepositoryInterface $scriptRepository,
        EntityRepositoryInterface $appRepository,
        string $projectDir
    ) {
        $this->scriptReader = $scriptReader;
        $this->scriptRepository = $scriptRepository;
        $this->appRepository = $appRepository;
        $this->projectDir = $projectDir;
    }

    public function updateScripts(string $appPath, string $appId, Context $context): void
    {
        $app = $this->getAppWithExistingScripts($appId, $context);
        /** @var ScriptCollection $existingScripts */
        $existingScripts = $app->getScripts();
        $scriptPaths = $this->scriptReader->getScriptPathsForApp($appPath);

        $upserts = [];
        foreach ($scriptPaths as $scriptPath) {
            $payload = [
                'script' => $this->scriptReader->getScriptContent($scriptPath, $appPath),
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
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $criteria->addFilter(new EqualsFilter('active', false));

        $scripts = $this->scriptRepository->searchIds($criteria, $context);

        $updateSet = array_map(function (string $id) {
            return ['id' => $id, 'active' => true];
        }, $scripts->getIds());

        $this->scriptRepository->update($updateSet, $context);
    }

    public function deactivateAppScripts(string $appId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $criteria->addFilter(new EqualsFilter('active', true));

        $scripts = $this->scriptRepository->searchIds($criteria, $context);

        $updateSet = array_map(function (string $id) {
            return ['id' => $id, 'active' => false];
        }, $scripts->getIds());

        $this->scriptRepository->update($updateSet, $context);
    }

    public function refresh(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));

        $apps = $this->appRepository->search($criteria, Context::createDefaultContext())->getEntities();

        /** @var AppEntity $app */
        foreach ($apps as $app) {
            $this->updateScripts($this->projectDir . $app->getPath(), $app->getId(), Context::createDefaultContext());
        }
    }

    private function deleteOldScripts(ScriptCollection $toBeRemoved, Context $context): void
    {
        /** @var string[] $ids */
        $ids = $toBeRemoved->getIds();

        if (!empty($ids)) {
            $ids = array_map(static function (string $id): array {
                return ['id' => $id];
            }, array_values($ids));

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
