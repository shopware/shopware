<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Feature;

use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppRemovalContext;
use Shopware\Core\Framework\App\Lifecycle\Handler\AbstractLifecycleHandler;
use Shopware\Core\Framework\Log\Package;

/**
 * Syncs the features declared in an app's manifest to the `app_feature` table on
 * install and update, and removes them on uninstall/delete unless the user chose
 * to keep app data.
 *
 * @internal
 */
#[Package('framework')]
class AppFeatureLifecycleHandler extends AbstractLifecycleHandler
{
    public function __construct(
        private readonly AppFeatureDefinitionRegistry $registry,
        private readonly AppFeatureStorage $storage,
    ) {
    }

    public function install(AppPersistContext $context): void
    {
        $this->storage->reattachKeptFeatures($context->app->getId(), $context->app->getName());
        $this->persist($context);
    }

    public function update(AppPersistContext $context): void
    {
        $this->persist($context);
    }

    public function uninstall(AppRemovalContext $context): void
    {
        $this->cleanup($context);
    }

    public function delete(AppRemovalContext $context): void
    {
        $this->cleanup($context);
    }

    private function persist(AppPersistContext $context): void
    {
        $appId = $context->app->getId();
        $rows = [];

        foreach ($this->registry->all() as $definition) {
            $stored = [];
            foreach ($this->storage->forApp($appId, $definition->getConfigClass()) as $feature) {
                $stored[$feature->config->getName()] = $feature->config;
            }

            foreach ($definition->fromApp($context->manifest, $context->appFilesystem, $context->defaultLocale) as $config) {
                $rows[] = [
                    'type' => $definition->getType(),
                    'name' => $config->getName(),
                    'payload' => $definition->toPayload($config, $stored[$config->getName()] ?? null),
                ];
            }
        }

        $this->storage->syncForApp($appId, $context->app->getName(), $rows);
    }

    private function cleanup(AppRemovalContext $context): void
    {
        if ($context->keepUserData) {
            // rows are kept: the foreign key sets their app_id to null when the app row
            // is deleted, and a later install of the same app re-attaches them by app name
            return;
        }

        $this->storage->deleteForApp($context->app->getId());
    }
}
