<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Subscriber;

use Shopware\Core\Content\GoogleShopping\Client\GoogleShoppingClient;
use Shopware\Core\Content\GoogleShopping\Exception\SalesChannelIsNotGoogleShoppingTypeException;
use Shopware\Core\Content\GoogleShopping\GoogleShoppingRequestValueResolver;
use Shopware\Core\Content\GoogleShopping\Service\GoogleShoppingAccount;
use Shopware\Core\Content\GoogleShopping\Service\GoogleShoppingDatafeed;
use Shopware\Core\Framework\Api\Exception\InvalidSalesChannelIdException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use function Flag\next6050;

class DatafeedProductExportSubscriber implements EventSubscriberInterface
{
    /** @var EntityRepositoryInterface */
    private $productExportRepository;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var GoogleShoppingAccount
     */
    private $googleShoppingAccount;

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    /**
     * @var GoogleShoppingClient
     */
    private $googleShoppingClient;

    /**
     * @var GoogleShoppingDatafeed
     */
    private $googleShoppingDatafeed;

    public function __construct(
        EntityRepositoryInterface $productExportRepository,
        GoogleShoppingAccount $googleShoppingAccount,
        EntityRepositoryInterface $salesChannelRepository,
        GoogleShoppingClient $googleShoppingClient,
        GoogleShoppingDatafeed $googleShoppingDatafeed,
        RequestStack $requestStack
    ) {
        $this->productExportRepository = $productExportRepository;
        $this->googleShoppingAccount = $googleShoppingAccount;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->googleShoppingClient = $googleShoppingClient;
        $this->googleShoppingDatafeed = $googleShoppingDatafeed;
        $this->requestStack = $requestStack;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'product_export.written' => 'writeDatafeed',
        ];
    }

    public function writeDatafeed(EntityWrittenEvent $event): void
    {
        if (!next6050()) {
            return;
        }

        foreach ($event->getWriteResults() as $writeResult) {
            if (!$this->productExportWritten($writeResult)) {
                continue;
            }

            $payload = $writeResult->getPayload();

            $resolver = new GoogleShoppingRequestValueResolver(
                $this->salesChannelRepository,
                $this->googleShoppingClient,
                $this->googleShoppingAccount
            );

            try {
                $salesChannel = $resolver->fetchGoogleShoppingSalesChannel($payload['salesChannelId'], $event->getContext());
            } catch (InvalidSalesChannelIdException $ex) {
                break;
            } catch (SalesChannelIsNotGoogleShoppingTypeException $ex) {
                break;
            }

            if (!$this->validateConnectedGoogleShoppingAccount($salesChannel)) {
                break;
            }

            $resolver->setGoogleShoppingAccountToClient($salesChannel->getGoogleShoppingAccount(), $event->getContext());

            $merchantAccount = $salesChannel->getGoogleShoppingAccount()->getGoogleShoppingMerchantAccount();

            $this->googleShoppingDatafeed->write($merchantAccount, $salesChannel, $event->getContext());
        }
    }

    private function validateConnectedGoogleShoppingAccount(SalesChannelEntity $salesChannel): bool
    {
        if (!$googleShoppingAccountEntity = $salesChannel->getGoogleShoppingAccount()) {
            return false;
        }

        if (!$googleShoppingAccountEntity->getGoogleShoppingMerchantAccount()) {
            return false;
        }

        return true;
    }

    private function productExportWritten(EntityWriteResult $writeResult): bool
    {
        return $writeResult->getEntityName() === 'product_export'
            && $writeResult->getOperation() !== EntityWriteResult::OPERATION_DELETE
            && !array_key_exists('generatedAt', $writeResult->getPayload());
    }
}
