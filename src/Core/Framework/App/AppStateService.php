<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use Shopware\Core\Framework\App\Lifecycle\AppManager;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class AppStateService
{
    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly AppManager $appManager,
        private readonly EntityRepository $appRepository,
    ) {
    }

    public function activateApp(string $appId, Context $context): void
    {
        $this->appManager->activate($this->loadApp($appId, $context), $context);
    }

    public function deactivateApp(string $appId, Context $context, bool $deactivateForDeletion = false): void
    {
        $this->appManager->deactivate($this->loadApp($appId, $context), $context, $deactivateForDeletion);
    }

    private function loadApp(string $appId, Context $context): AppEntity
    {
        $app = $this->appRepository->search(new Criteria([$appId]), $context)->getEntities()->first();
        if (!$app instanceof AppEntity) {
            throw AppException::notFound($appId);
        }

        return $app;
    }
}
