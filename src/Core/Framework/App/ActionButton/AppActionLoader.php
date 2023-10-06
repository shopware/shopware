<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ActionButton;

use Shopware\Core\Framework\App\Aggregate\ActionButton\ActionButtonEntity;
use Shopware\Core\Framework\App\Exception\ActionNotFoundException;
use Shopware\Core\Framework\App\Exception\AppUrlChangeDetectedException;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
#[Package('core')]
class AppActionLoader
{
    public function __construct(
        private readonly string $url,
        private readonly EntityRepository $actionButtonRepo,
        private readonly ShopIdProvider $shopIdProvider
    ) {
    }

    /**
     * @param array<string> $ids
     */
    public function loadAppAction(string $actionId, array $ids, Context $context): AppAction
    {
        $criteria = new Criteria([$actionId]);
        $criteria->addAssociation('app.integration');

        /** @var ActionButtonEntity|null $actionButton */
        $actionButton = $this->actionButtonRepo->search($criteria, $context)->first();

        if ($actionButton === null) {
            throw new ActionNotFoundException();
        }

        try {
            $shopId = $this->shopIdProvider->getShopId();
        } catch (AppUrlChangeDetectedException) {
            throw new ActionNotFoundException();
        }

        /** @var string $secret */
        $secret = $actionButton->getApp()->getAppSecret();

        return new AppAction(
            $actionButton->getUrl(),
            $this->url,
            $actionButton->getApp()->getVersion(),
            $actionButton->getEntity(),
            $actionButton->getAction(),
            $ids,
            $secret,
            $shopId,
            $actionId
        );
    }
}
