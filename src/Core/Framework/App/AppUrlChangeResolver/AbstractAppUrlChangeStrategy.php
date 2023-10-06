<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\AppUrlChangeResolver;

use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
abstract class AbstractAppUrlChangeStrategy
{
    public function __construct(
        private readonly AbstractAppLoader $appLoader,
        private readonly EntityRepository $appRepository,
        private readonly AppRegistrationService $registrationService
    ) {
    }

    abstract public function getName(): string;

    abstract public function getDescription(): string;

    abstract public function resolve(Context $context): void;

    abstract public function getDecorated(): self;

    /**
     * @param callable(Manifest, AppEntity, Context): void $callback
     */
    protected function forEachInstalledApp(Context $context, callable $callback): void
    {
        $manifests = $this->appLoader->load();
        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $context)->getEntities();

        foreach ($manifests as $manifest) {
            $app = $this->getAppForManifest($manifest, $apps);

            if (!$app || !$manifest->getSetup()) {
                continue;
            }

            $callback($manifest, $app, $context);
        }
    }

    protected function reRegisterApp(Manifest $manifest, AppEntity $app, Context $context): void
    {
        $secret = AccessKeyHelper::generateSecretAccessKey();

        $this->appRepository->update([
            [
                'id' => $app->getId(),
                'integration' => [
                    'id' => $app->getIntegrationId(),
                    'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
                    'secretAccessKey' => $secret,
                ],
            ],
        ], $context);

        $this->registrationService->registerApp($manifest, $app->getId(), $secret, $context);
    }

    private function getAppForManifest(Manifest $manifest, AppCollection $installedApps): ?AppEntity
    {
        $matchedApps = $installedApps->filter(static fn (AppEntity $installedApp): bool => $installedApp->getName() === $manifest->getMetadata()->getName());

        return $matchedApps->first();
    }
}
