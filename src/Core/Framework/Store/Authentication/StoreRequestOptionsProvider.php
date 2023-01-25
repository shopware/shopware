<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Authentication;

use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceUserException;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\InstanceService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\User\UserEntity;

/**
 * @internal
 */
#[Package('merchant-services')]
class StoreRequestOptionsProvider extends AbstractStoreRequestOptionsProvider
{
    final public const CONFIG_KEY_STORE_LICENSE_DOMAIN = 'core.store.licenseHost';
    final public const CONFIG_KEY_STORE_SHOP_SECRET = 'core.store.shopSecret';

    private const SHOPWARE_PLATFORM_TOKEN_HEADER = 'X-Shopware-Platform-Token';
    private const SHOPWARE_SHOP_SECRET_HEADER = 'X-Shopware-Shop-Secret';

    public function __construct(
        private readonly EntityRepository $userRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly InstanceService $instanceService,
        private readonly LocaleProvider $localeProvider,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function getAuthenticationHeader(Context $context): array
    {
        return array_filter([
            self::SHOPWARE_PLATFORM_TOKEN_HEADER => $this->getUserStoreToken($context),
            self::SHOPWARE_SHOP_SECRET_HEADER => $this->systemConfigService->getString(self::CONFIG_KEY_STORE_SHOP_SECRET),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function getDefaultQueryParameters(Context $context): array
    {
        return [
            'shopwareVersion' => $this->instanceService->getShopwareVersion(),
            'language' => $this->localeProvider->getLocaleFromContext($context),
            'domain' => $this->getLicenseDomain(),
        ];
    }

    private function getUserStoreToken(Context $context): ?string
    {
        try {
            return $this->getTokenFromAdmin($context);
        } catch (InvalidContextSourceException) {
            return $this->getTokenFromSystem($context);
        }
    }

    private function getTokenFromAdmin(Context $context): ?string
    {
        $contextSource = $this->ensureAdminApiSource($context);
        $userId = $contextSource->getUserId();
        if ($userId === null) {
            throw new InvalidContextSourceUserException($contextSource::class);
        }

        return $this->fetchUserStoreToken(new Criteria([$userId]), $context);
    }

    private function getTokenFromSystem(Context $context): ?string
    {
        $contextSource = $context->getSource();
        if (!($contextSource instanceof SystemSource)) {
            throw new InvalidContextSourceException(SystemSource::class, $contextSource::class);
        }

        $criteria = new Criteria();
        $criteria->addFilter(
            new NotFilter(NotFilter::CONNECTION_OR, [new EqualsFilter('storeToken', null)])
        );

        return $this->fetchUserStoreToken($criteria, $context);
    }

    private function fetchUserStoreToken(Criteria $criteria, Context $context): ?string
    {
        /** @var UserEntity|null $user */
        $user = $this->userRepository->search($criteria, $context)->first();

        if ($user === null) {
            return null;
        }

        return $user->getStoreToken();
    }

    private function getLicenseDomain(): string
    {
        /** @var string $domain */
        $domain = $this->systemConfigService->get(self::CONFIG_KEY_STORE_LICENSE_DOMAIN) ?? '';

        return $domain;
    }
}
