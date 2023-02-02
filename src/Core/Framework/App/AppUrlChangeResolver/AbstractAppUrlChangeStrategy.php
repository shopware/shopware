<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\AppUrlChangeResolver;

use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * @internal only for use by the app-system
 */
abstract class AbstractAppUrlChangeStrategy
{
    /**
     * @var AbstractAppLoader
     */
    private $appLoader;

    /**
     * @var EntityRepositoryInterface
     */
    private $appRepository;

    /**
     * @var AppRegistrationService
     */
    private $registrationService;

    public function __construct(
        AbstractAppLoader $appLoader,
        EntityRepositoryInterface $appRepository,
        AppRegistrationService $registrationService
    ) {
        $this->appLoader = $appLoader;
        $this->appRepository = $appRepository;
        $this->registrationService = $registrationService;
    }

    abstract public function getName(): string;

    abstract public function getDescription(): string;

    abstract public function resolve(Context $context): void;

    abstract public function getDecorated(): self;

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
        $matchedApps = $installedApps->filter(static function (AppEntity $installedApp) use ($manifest): bool {
            return $installedApp->getName() === $manifest->getMetadata()->getName();
        });

        return $matchedApps->first();
    }
}
