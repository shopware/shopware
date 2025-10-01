<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Exception\AppUrlVerificationFailed;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\Url\AppUrlVerifier;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('framework')]
#[AsMessageHandler(handles: VerifyAppUrlTask::class)]
final class VerifyAppUrlTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $repository
     */
    public function __construct(
        EntityRepository $repository,
        LoggerInterface $logger,
        private readonly ShopIdProvider $shopIdProvider,
        private readonly AppUrlVerifier $appUrlVerifier,
    ) {
        parent::__construct($repository, $logger);
    }

    public function run(): void
    {
        try {
            $shopId = $this->shopIdProvider->getShopId();
        } catch (ShopIdChangeSuggestedException) {
            // if shop id invalid, no point checking
            return;
        } catch (AppUrlVerificationFailed $e) {
            // the previous attempt failed, check again
            $shopId = $e->getShopId();
        }

        $this->appUrlVerifier->verify($shopId);
    }
}
