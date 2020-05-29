<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Service;

use Shopware\Core\Content\GoogleShopping\Client\Adapter\UserProfileResource;
use Shopware\Core\Content\GoogleShopping\DataAbstractionLayer\GoogleAccountCredential;
use Shopware\Core\Content\GoogleShopping\Event\GoogleAccountCredentialCreatedEvent;
use Shopware\Core\Content\GoogleShopping\Event\GoogleAccountCredentialDeletedEvent;
use Shopware\Core\Content\GoogleShopping\Event\GoogleAccountCredentialRefreshedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class GoogleShoppingAccount
{
    /**
     * @var EntityRepositoryInterface
     */
    private $googleShoppingAccountRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var UserProfileResource
     */
    private $userProfileResource;

    public function __construct(
        EntityRepositoryInterface $googleShoppingAccountRepository,
        UserProfileResource $userProfileResource,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->googleShoppingAccountRepository = $googleShoppingAccountRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->userProfileResource = $userProfileResource;
    }

    public function getProfile(): array
    {
        return $this->userProfileResource->get();
    }

    public function create(GoogleAccountCredential $credential, string $salesChannelId, Context $context): void
    {
        $idTokenParts = $credential->getIdTokenParts();

        $account = [
            'salesChannelId' => $salesChannelId,
            'credential' => $credential,
            'name' => $idTokenParts['name'],
            'email' => $idTokenParts['email'],
        ];

        $this->googleShoppingAccountRepository->create([$account], $context);
        $this->eventDispatcher->dispatch(new GoogleAccountCredentialCreatedEvent($credential, $context));
    }

    public function updateCredential(string $id, GoogleAccountCredential $credential, Context $context): void
    {
        $account = [
            'id' => $id,
            'credential' => $credential,
        ];

        $this->googleShoppingAccountRepository->update([$account], $context);
        $this->eventDispatcher->dispatch(new GoogleAccountCredentialRefreshedEvent($credential, $context));
    }

    public function delete(string $id, GoogleAccountCredential $googleAccountCredential, Context $context): void
    {
        $this->googleShoppingAccountRepository->delete([['id' => $id]], $context);
        $this->eventDispatcher->dispatch(new GoogleAccountCredentialDeletedEvent($googleAccountCredential, $context));
    }

    public function acceptTermOfService(string $id, bool $accept, Context $context)
    {
        $tosAcceptedAt = $accept ? new \DateTime() : null;

        return $this->googleShoppingAccountRepository->update([['id' => $id, 'tosAcceptedAt' => $tosAcceptedAt]], $context);
    }
}
