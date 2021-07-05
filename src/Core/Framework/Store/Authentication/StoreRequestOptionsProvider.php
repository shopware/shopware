<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Authentication;

use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceUserException;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Store\Exception\StoreTokenMissingException;
use Shopware\Core\Framework\Store\Services\InstanceService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\User\UserEntity;

class StoreRequestOptionsProvider extends AbstractStoreRequestOptionsProvider
{
    public const CONFIG_KEY_STORE_LICENSE_DOMAIN = 'core.store.licenseHost';
    public const CONFIG_KEY_STORE_SHOP_SECRET = 'core.store.shopSecret';

    private const SHOPWARE_PLATFORM_TOKEN_HEADER = 'X-Shopware-Platform-Token';
    private const SHOPWARE_SHOP_SECRET_HEADER = 'X-Shopware-Shop-Secret';

    private EntityRepositoryInterface $userRepository;

    private SystemConfigService $systemConfigService;

    private InstanceService $instanceService;

    private LocaleProvider $localeProvider;

    public function __construct(
        EntityRepositoryInterface $userRepository,
        SystemConfigService $systemConfigService,
        InstanceService $instanceService,
        LocaleProvider $localeProvider
    ) {
        $this->userRepository = $userRepository;
        $this->systemConfigService = $systemConfigService;
        $this->instanceService = $instanceService;
        $this->localeProvider = $localeProvider;
    }

    public function getAuthenticationHeader(Context $context): array
    {
        return array_filter([
            self::SHOPWARE_PLATFORM_TOKEN_HEADER => $this->getUserStoreToken($context),
            self::SHOPWARE_SHOP_SECRET_HEADER => $this->systemConfigService->get(self::CONFIG_KEY_STORE_SHOP_SECRET),
        ]);
    }

    public function getDefaultQueryParameters(?Context $context, ?string $language = null): array
    {
        $queries = [
            'shopwareVersion' => $this->instanceService->getShopwareVersion(),
            'language' => $this->getLanguage($context, $language),
            'domain' => $this->getLicenseDomain(),
        ];

        return array_filter($queries);
    }

    private function getUserStoreToken(Context $context): string
    {
        try {
            return $this->getTokenFromAdmin($context);
        } catch (InvalidContextSourceException $e) {
            return $this->getTokenFromSystem($context);
        }
    }

    private function getTokenFromAdmin(Context $context): string
    {
        $contextSource = $this->ensureAdminApiSource($context);
        $userId = $contextSource->getUserId();
        if ($userId === null) {
            throw new InvalidContextSourceUserException(\get_class($contextSource));
        }

        /** @var UserEntity|null $user */
        $user = $this->userRepository->search(new Criteria([$userId]), $context)->first();

        if ($user === null) {
            throw new StoreTokenMissingException();
        }

        $storeToken = $user->getStoreToken();
        if ($storeToken === null) {
            throw new StoreTokenMissingException();
        }

        return $storeToken;
    }

    private function getTokenFromSystem(Context $context): string
    {
        $contextSource = $context->getSource();
        if (!($contextSource instanceof SystemSource)) {
            throw new InvalidContextSourceException(SystemSource::class, \get_class($contextSource));
        }

        $criteria = new Criteria();
        $criteria->addFilter(
            new NotFilter(NotFilter::CONNECTION_OR, [new EqualsFilter('storeToken', null)])
        );

        /** @var UserEntity|null $user */
        $user = $this->userRepository->search($criteria, $context)->first();

        if ($user === null) {
            throw new StoreTokenMissingException();
        }

        $storeToken = $user->getStoreToken();
        if ($storeToken === null) {
            throw new StoreTokenMissingException();
        }

        return $storeToken;
    }

    private function getLanguage(?Context $context, ?string $language): string
    {
        if ($language !== null && $language !== '') {
            return $language;
        }

        if ($context === null) {
            return 'en-GB';
        }

        return $this->localeProvider->getLocaleFromContext($context);
    }

    private function getLicenseDomain(): ?string
    {
        /** @var string|null $domain */
        $domain = $this->systemConfigService->get(self::CONFIG_KEY_STORE_LICENSE_DOMAIN);

        return $domain;
    }
}
