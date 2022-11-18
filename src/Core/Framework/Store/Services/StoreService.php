<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Store\Struct\AccessTokenStruct;
use Shopware\Core\System\User\UserEntity;

/**
 * @internal
 */
class StoreService
{
    public const CONFIG_KEY_STORE_LICENSE_DOMAIN = 'core.store.licenseHost';
    public const CONFIG_KEY_STORE_LICENSE_EDITION = 'core.store.licenseEdition';

    private EntityRepository $userRepository;

    private TrackingEventClient $trackingEventClient;

    final public function __construct(
        EntityRepository $userRepository,
        TrackingEventClient $trackingEventClient
    ) {
        $this->userRepository = $userRepository;
        $this->trackingEventClient = $trackingEventClient;
    }

    /**
     * @deprecated tag:v6.5.0 - Use Shopware\Core\Framework\Store\Services\TrackingEventClient::fireTrackingEvent() instead
     *
     * @param mixed[] $additionalData
     *
     * @return mixed[]|null
     */
    public function fireTrackingEvent(string $eventName, array $additionalData = []): ?array
    {
        Feature::triggerDeprecationOrThrow('v6.5.0.0', Feature::deprecatedMethodMessage(
            __CLASS__,
            __METHOD__,
            'v6.5.0.0',
            'TrackingEventClient::fireTrackingEvent()'
        ));

        return $this->trackingEventClient->fireTrackingEvent($eventName, $additionalData);
    }

    /**
     * @deprecated tag:v6.5.0 - Use Shopware\Core\Framework\Store\Authentication\LocaleProvider::getLocaleFromContext() instead
     */
    public function getLanguageByContext(Context $context): string
    {
        Feature::triggerDeprecationOrThrow('v6.5.0.0', Feature::deprecatedMethodMessage(
            __CLASS__,
            __METHOD__,
            'v6.5.0.0',
            'LocaleProvider::getLocaleFromContext()'
        ));

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
