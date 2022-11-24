<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Authentication;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Store\Services\FirstRunWizardClient;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigEntity;

/**
 * @package merchant-services
 *
 * @deprecated tag:v6.5.0 - reason:becomes-internal
 */
class FrwRequestOptionsProvider extends AbstractStoreRequestOptionsProvider
{
    private const SHOPWARE_TOKEN_HEADER = 'X-Shopware-Token';

    private AbstractStoreRequestOptionsProvider $optionsProvider;

    private EntityRepository $userConfigRepository;

    /**
     * @internal
     */
    public function __construct(
        AbstractStoreRequestOptionsProvider $optionsProvider,
        EntityRepository $userConfigRepository
    ) {
        $this->optionsProvider = $optionsProvider;
        $this->userConfigRepository = $userConfigRepository;
    }

    public function getAuthenticationHeader(Context $context): array
    {
        return array_filter([self::SHOPWARE_TOKEN_HEADER => $this->getFrwUserToken($context)]);
    }

    /**
     * @deprecated tag:v6.5.0 - parameter $language will be removed and $context must not be null in the future
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

        if (!Feature::isActive('v6.5.0.0')) {
            return $this->optionsProvider->getDefaultQueryParameters($context, $language);
        }

        return $this->optionsProvider->getDefaultQueryParameters($context);
    }

    private function getFrwUserToken(Context $context): ?string
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            throw new InvalidContextSourceException(AdminApiSource::class, \get_class($context->getSource()));
        }

        /** @var AdminApiSource $contextSource */
        $contextSource = $context->getSource();

        $criteria = (new Criteria())->addFilter(
            new EqualsFilter('userId', $contextSource->getUserId()),
            new EqualsFilter('key', FirstRunWizardClient::USER_CONFIG_KEY_FRW_USER_TOKEN),
        );

        /** @var UserConfigEntity|null $userConfig */
        $userConfig = $this->userConfigRepository->search($criteria, $context)->first();

        return $userConfig === null ? null : $userConfig->getValue()[FirstRunWizardClient::USER_CONFIG_VALUE_FRW_USER_TOKEN] ?? null;
    }
}
