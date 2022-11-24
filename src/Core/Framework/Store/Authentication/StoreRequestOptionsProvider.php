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
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Store\Services\InstanceService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\User\UserEntity;

/**
 * @package merchant-services
 *
 * @deprecated tag:v6.5.0 - reason:becomes-internal - will be internal in future versions
 */
class StoreRequestOptionsProvider extends AbstractStoreRequestOptionsProvider
{
    public const CONFIG_KEY_STORE_LICENSE_DOMAIN = 'core.store.licenseHost';
    public const CONFIG_KEY_STORE_SHOP_SECRET = 'core.store.shopSecret';

    private const SHOPWARE_PLATFORM_TOKEN_HEADER = 'X-Shopware-Platform-Token';
    private const SHOPWARE_SHOP_SECRET_HEADER = 'X-Shopware-Shop-Secret';

    private EntityRepository $userRepository;

    private SystemConfigService $systemConfigService;

    private InstanceService $instanceService;

    private LocaleProvider $localeProvider;

    /**
     * @internal
     */
    public function __construct(
        EntityRepository $userRepository,
        SystemConfigService $systemConfigService,
        InstanceService $instanceService,
        LocaleProvider $localeProvider
    ) {
        $this->userRepository = $userRepository;
        $this->systemConfigService = $systemConfigService;
        $this->instanceService = $instanceService;
        $this->localeProvider = $localeProvider;
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
     * @deprecated tag:v6.5.0 - parameter $language will be removed and $context must not be null in the future
     *
     * @return array<string, string>
     */
    public function getDefaultQueryParameters(?Context $context, ?string $language = null): array
    {
        if ($context === null) {
            Feature::triggerDeprecationOrThrow(
                'v6.5.0.0',
                'First parameter `$context` of method "getDefaultQueryParameters()" in "StoreRequestOptionsProvider" will be required in v6.5.0.0.'
            );
        }

        if (\func_num_args() > 1) {
            Feature::triggerDeprecationOrThrow(
                'v6.5.0.0',
                'Second parameter `$language` of method "getDefaultQueryParameters()" in "StoreRequestOptionsProvider" is deprecated and will be removed in v6.5.0.0.'
            );
        }

        return [
            'shopwareVersion' => $this->instanceService->getShopwareVersion(),
            'language' => $this->getLanguage($context, $language),
            'domain' => $this->getLicenseDomain(),
        ];
    }

    private function getUserStoreToken(Context $context): ?string
    {
        try {
            return $this->getTokenFromAdmin($context);
        } catch (InvalidContextSourceException $e) {
            return $this->getTokenFromSystem($context);
        }
    }

    private function getTokenFromAdmin(Context $context): ?string
    {
        $contextSource = $this->ensureAdminApiSource($context);
        $userId = $contextSource->getUserId();
        if ($userId === null) {
            throw new InvalidContextSourceUserException(\get_class($contextSource));
        }

        return $this->fetchUserStoreToken(new Criteria([$userId]), $context);
    }

    private function getTokenFromSystem(Context $context): ?string
    {
        $contextSource = $context->getSource();
        if (!($contextSource instanceof SystemSource)) {
            throw new InvalidContextSourceException(SystemSource::class, \get_class($contextSource));
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

    private function getLicenseDomain(): string
    {
        /** @var string $domain */
        $domain = $this->systemConfigService->get(self::CONFIG_KEY_STORE_LICENSE_DOMAIN) ?? '';

        return $domain;
    }
}
