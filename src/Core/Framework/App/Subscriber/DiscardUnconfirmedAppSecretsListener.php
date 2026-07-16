<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Subscriber;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Shopware\Core\Framework\App\ShopId\ShopIdDeletedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Deleting the shop id abandons every registration keyed to it, so unconfirmed secret candidates can
 * never repair one again — discard them before the apps are re-registered or removed under the new
 * identity. Same-identity moves never delete the shop id, so they keep their candidates for recovery.
 */
#[Package('framework')]
class DiscardUnconfirmedAppSecretsListener
{
    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly EntityRepository $appRepository,
        private readonly AppSecretRotationService $appSecretRotationService,
    ) {
    }

    public function __invoke(ShopIdDeletedEvent $event): void
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addFilter(new NotEqualsFilter('unconfirmedAppSecrets', null));

        /** @var list<string> $appIds */
        $appIds = $this->appRepository->searchIds($criteria, $context)->getIds();

        foreach ($appIds as $appId) {
            $this->appSecretRotationService->discardNow($appId, $context);
        }
    }
}
