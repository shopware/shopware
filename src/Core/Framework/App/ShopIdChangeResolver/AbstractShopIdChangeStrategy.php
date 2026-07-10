<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ShopIdChangeResolver;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
abstract class AbstractShopIdChangeStrategy
{
    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly SourceResolver $sourceResolver,
        private readonly EntityRepository $appRepository,
        private readonly AppSecretRotationService $appSecretRotationService
    ) {
    }

    abstract public function getName(): string;

    /**
     * @return string the description of the strategy used to explain what the strategy does in CLI and API
     *
     * Note: in the administration we have separate snippets for this to localize the description, keep the descriptions in sync
     * `sw-app.component.sw-app-shop-id-change-modal.strategies.${strategy-name}.description`
     */
    abstract public function getDescription(): string;

    abstract public function resolve(Context $context): void;

    abstract public function getDecorated(): self;

    /**
     * @param callable(Manifest, AppEntity, Context): void $callback
     */
    protected function forEachInstalledApp(Context $context, callable $callback): void
    {
        $apps = $this->appRepository->search(new Criteria(), $context);

        foreach ($apps as $app) {
            $fs = $this->sourceResolver->filesystemForApp($app);
            $path = $fs->hasFile('manifest.local.xml') ? 'manifest.local.xml' : 'manifest.xml';
            $manifest = Manifest::createFromXmlFile($fs->path($path));

            if (!$manifest->getSetup()) {
                continue;
            }

            $callback($manifest, $app, $context);
        }
    }

    protected function reRegisterApp(Manifest $manifest, AppEntity $app, Context $context): void
    {
        $this->appSecretRotationService->rotateNow($app->getId(), $context, AppSecretRotationService::TRIGGER_SHOP_MOVE);
    }
}
