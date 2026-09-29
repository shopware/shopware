<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Files;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppRemovalContext;
use Shopware\Core\Framework\App\Lifecycle\Handler\AbstractLifecycleHandler;
use Shopware\Core\Framework\Log\Package;

/**
 * Snapshots an app's storefront snippet files into the shared storage on install and update, so that a
 * storefront request never has to resolve the app source once the app went through this handler.
 *
 * @internal
 */
#[Package('discovery')]
class StorefrontSnippetLifecycleHandler extends AbstractLifecycleHandler
{
    public function __construct(
        private readonly StorefrontSnippetStorage $snippetStorage,
        private readonly CacheInvalidator $cacheInvalidator,
        private readonly Connection $connection,
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

    public function uninstall(AppRemovalContext $context): void
    {
        $this->remove($context->app->getName());
    }

    public function delete(AppRemovalContext $context): void
    {
        $this->remove($context->app->getName());
    }

    private function persist(AppPersistContext $context): void
    {
        $changed = $this->snippetStorage->persist(
            $context->app->getName(),
            $context->app->getVersion(),
            $context->appFilesystem
        );

        if ($changed) {
            $this->invalidateTranslatorCache();
        }
    }

    private function remove(string $appName): void
    {
        if ($this->snippetStorage->remove($appName)) {
            $this->invalidateTranslatorCache();
        }
    }

    private function invalidateTranslatorCache(): void
    {
        /** @var list<string> $snippetSetIds */
        $snippetSetIds = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(`id`)) FROM `snippet_set`');

        $this->cacheInvalidator->invalidate(array_map(Translator::tag(...), $snippetSetIds), true);
    }
}
