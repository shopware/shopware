<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Store\Authentication\AbstractStoreRequestOptionsProvider;
use Shopware\Core\Framework\Store\Struct\AccessTokenStruct;
use Shopware\Core\System\User\UserEntity;

/**
 * @internal
 */
class StoreService
{
    public const CONFIG_KEY_STORE_LICENSE_DOMAIN = 'core.store.licenseHost';
    public const CONFIG_KEY_STORE_LICENSE_EDITION = 'core.store.licenseEdition';

    private EntityRepositoryInterface $userRepository;

    private InstanceService $instanceService;

    private AbstractStoreRequestOptionsProvider $requestOptionsProvider;

    final public function __construct(
        EntityRepositoryInterface $userRepository,
        InstanceService $instanceService,
        AbstractStoreRequestOptionsProvider $requestOptionsProvider
    ) {
        $this->userRepository = $userRepository;
        $this->instanceService = $instanceService;
        $this->requestOptionsProvider = $requestOptionsProvider;
    }

    /**
     * @deprecated tag:v6.5.0 Will be removed. Use getDefaultQueryParametersFromContext instead
     */
    public function getDefaultQueryParameters(string $language, bool $checkLicenseDomain = true): array
    {
        return $this->requestOptionsProvider->getDefaultQueryParameters(null, $language);
    }

    public function getDefaultQueryParametersFromContext(Context $context): array
    {
        return $this->requestOptionsProvider->getDefaultQueryParameters($context);
    }

    /**
     * @deprecated tag:v6.5.0 Use InstanceService::getShopwareVersion instead
     */
    public function getShopwareVersion(): string
    {
        return $this->instanceService->getShopwareVersion();
    }

    public function getLanguageByContext(Context $context): string
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            return 'en-GB';
        }

        /** @var AdminApiSource $source */
        $source = $context->getSource();

        if ($source->getUserId() === null) {
            return 'en-GB';
        }

        $criteria = new Criteria([$source->getUserId()]);
        $criteria->addAssociation('locale');

        /** @var UserEntity $user */
        $user = $this->userRepository->search($criteria, $context)->first();

        if ($user->getLocale() === null) {
            return 'en-GB';
        }

        return $user->getLocale()->getCode();
    }

    public function updateStoreToken(Context $context, AccessTokenStruct $accessToken): void
    {
        /** @var AdminApiSource $contextSource */
        $contextSource = $context->getSource();
        $userId = $contextSource->getUserId();

        $storeToken = $accessToken->getShopUserToken()->getToken();

        $context->scope(Context::SYSTEM_SCOPE, function ($context) use ($userId, $storeToken): void {
            $this->userRepository->update([['id' => $userId, 'storeToken' => $storeToken]], $context);
        });
    }
}
