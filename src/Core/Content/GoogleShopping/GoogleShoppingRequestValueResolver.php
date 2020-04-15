<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping;

use Shopware\Core\Content\GoogleShopping\Client\GoogleShoppingClient;
use Shopware\Core\Content\GoogleShopping\DataAbstractionLayer\GoogleAccountCredential;
use Shopware\Core\Content\GoogleShopping\Exception\GoogleAuthenticationException;
use Shopware\Core\Content\GoogleShopping\Exception\SalesChannelIsNotGoogleShoppingTypeException;
use Shopware\Core\Content\GoogleShopping\Service\GoogleShoppingAccount;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Exception\InvalidSalesChannelIdException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class GoogleShoppingRequestValueResolver implements ArgumentValueResolverInterface
{
    /**
     * @var GoogleShoppingClient
     */
    private $googleShoppingClient;

    /**
     * @var GoogleShoppingAccount
     */
    private $googleShoppingAccountService;

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    public function __construct(
        EntityRepositoryInterface $salesChannelRepository,
        GoogleShoppingClient $googleShoppingClient,
        GoogleShoppingAccount $googleShoppingAccountService
    ) {
        $this->googleShoppingClient = $googleShoppingClient;
        $this->googleShoppingAccountService = $googleShoppingAccountService;
        $this->salesChannelRepository = $salesChannelRepository;
    }

    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === GoogleShoppingRequest::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $salesChannelId = $request->attributes->get('salesChannelId');
        $salesChannel = $this->fetchGoogleShoppingSalesChannel($salesChannelId, $request->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT));

        $googleRequest = new GoogleShoppingRequest(
            $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT),
            $salesChannel
        );

        if ($googleShoppingAccount = $googleRequest->getGoogleShoppingAccount()) {
            $this->setGoogleShoppingAccountToClient($googleShoppingAccount, $googleRequest);
        }

        yield $googleRequest;
    }

    private function setGoogleShoppingAccountToClient(GoogleShoppingAccountEntity $googleShoppingAccountEntity, GoogleShoppingRequest $context): void
    {
        $this->googleShoppingClient->setAccessToken($googleShoppingAccountEntity->getCredential()->normalize());

        if ($this->googleShoppingClient->isAccessTokenExpired()) {
            $newCredentialRaw = $this->googleShoppingClient->fetchAccessTokenWithRefreshToken();

            if (!empty($newCredentialRaw['error'])) {
                throw new GoogleAuthenticationException($newCredentialRaw['error'], $newCredentialRaw['error_description']);
            }

            $newCredential = new GoogleAccountCredential($newCredentialRaw);
            $this->googleShoppingAccountService->updateCredential($googleShoppingAccountEntity->getId(), $newCredential, $context);
        }
    }

    private function fetchGoogleShoppingSalesChannel(string $salesChannelId, Context $context): SalesChannelEntity
    {
        $criteria = new Criteria([$salesChannelId]);
        $criteria->addAssociation('googleShoppingAccount.googleShoppingMerchantAccount');

        $salesChannel = $this->salesChannelRepository->search($criteria, $context)->get($salesChannelId);

        if ($salesChannel === null) {
            throw new InvalidSalesChannelIdException($salesChannelId);
        }

        if ($salesChannel->getTypeId() !== Defaults::SALES_CHANNEL_TYPE_GOOGLE_SHOPPING) {
            throw new SalesChannelIsNotGoogleShoppingTypeException();
        }

        return $salesChannel;
    }
}
