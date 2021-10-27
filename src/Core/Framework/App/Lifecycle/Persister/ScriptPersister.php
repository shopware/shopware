<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Script\AppScriptCollection;
use Shopware\Core\Framework\App\Script\AppScriptEntity;
use Shopware\Core\Framework\App\Template\AbstractTemplateLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * @internal only for use by the app-system
 */
class ScriptPersister
{
    private AbstractTemplateLoader $scriptLoader;

    private EntityRepositoryInterface $scriptRepository;

    private EntityRepositoryInterface $appRepository;

    public function __construct(
        AbstractTemplateLoader $scriptLoader,
        EntityRepositoryInterface $scriptRepository,
        EntityRepositoryInterface $appRepository
    ) {
        $this->scriptLoader = $scriptLoader;
        $this->scriptRepository = $scriptRepository;
        $this->appRepository = $appRepository;
    }

    public function updateScripts(Manifest $manifest, string $appId, Context $context): void
    {
        $app = $this->getAppWithExistingScripts($appId, $context);
        /** @var AppScriptCollection $existingScripts */
        $existingScripts = $app->getScripts();
        $scriptPaths = $this->scriptLoader->getTemplatePathsForApp($manifest);

        $upserts = [];
        foreach ($scriptPaths as $scriptPath) {
            $payload = [
                'script' => $this->scriptLoader->getTemplateContent($scriptPath, $manifest),
            ];

            /** @var AppScriptEntity|null $existing */
            $existing = $existingScripts->filterByProperty('name', $scriptPath)->first();
            if ($existing) {
                $payload['id'] = $existing->getId();
                $existingScripts->remove($existing->getId());
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

    private function deleteOldScripts(AppScriptCollection $toBeRemoved, Context $context): void
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
