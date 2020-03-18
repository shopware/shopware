<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Service;

use Shopware\Core\Content\GoogleShopping\Client\Adapter\GoogleShoppingContentAccountResource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Uuid\Uuid;

class GoogleShoppingMerchantAccount
{
    /**
     * @var EntityRepositoryInterface
     */
    private $googleMerchantAccountRepository;

    /**
     * @var GoogleShoppingContentAccountResource
     */
    private $googleShoppingContentAccountResource;

    public function __construct(
        EntityRepositoryInterface $googleMerchantAccountRepository,
        GoogleShoppingContentAccountResource $googleShoppingContentAccountResource
    ) {
        $this->googleMerchantAccountRepository = $googleMerchantAccountRepository;
        $this->googleShoppingContentAccountResource = $googleShoppingContentAccountResource;
    }

    public function getInfo(string $merchantId): array
    {
        return $this->googleShoppingContentAccountResource->get($merchantId, $merchantId);
    }

    public function list(): array
    {
        $accounts = $this->googleShoppingContentAccountResource->list();

        return array_map(function ($account) {
            return [
                'id' => $account['id'],
                'name' => $account['name'],
            ];
        }, $accounts);
    }

    public function create(string $googleMerchantAccountId, string $googleShoppingAccountId, Context $context): string
    {
        $account = [
            'id' => Uuid::randomHex(),
            'merchantId' => $googleMerchantAccountId,
            'accountId' => $googleShoppingAccountId,
        ];

        $this->googleMerchantAccountRepository->create([$account], $context);

        return $account['id'];
    }

    public function delete(string $id, Context $context): EntityWrittenContainerEvent
    {
        return $this->googleMerchantAccountRepository->delete([['id' => $id]], $context);
    }
}
