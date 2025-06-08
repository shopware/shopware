<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\Consent;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigEntity;

/**
 * @internal
 */
#[Package('data-services')]
class BannerService
{
    public const USER_CONFIG_KEY_HIDE_CONSENT_BANNER = 'core.usageData.hideConsentBanner';

    public function __construct(private readonly EntityRepository $userConfigRepository)
    {
    }

    public function hideConsentBannerForUser(string $userId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('userId', $userId));
        $criteria->addFilter(new EqualsFilter('key', self::USER_CONFIG_KEY_HIDE_CONSENT_BANNER));

        $userConfigId = $this->userConfigRepository->searchIds($criteria, $context)->firstId();

        $this->userConfigRepository->upsert([
            [
                'id' => $userConfigId ?: Uuid::randomHex(),
                'userId' => $userId,
                'key' => self::USER_CONFIG_KEY_HIDE_CONSENT_BANNER,
                'value' => ['_value' => true],
            ],
        ], $context);
    }

    public function hasUserHiddenConsentBanner(string $userId, Context $context): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('key', self::USER_CONFIG_KEY_HIDE_CONSENT_BANNER));
        $criteria->addFilter(new EqualsFilter('userId', $userId));

        /** @var UserConfigEntity|null $userConfig */
        $userConfig = $this->userConfigRepository->search($criteria, $context)->first();
        if ($userConfig === null) {
            return false;
        }

        return $userConfig->getValue()['_value'] ?? false;
    }

    public function resetIsBannerHiddenForAllUsers(): void
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('key', self::USER_CONFIG_KEY_HIDE_CONSENT_BANNER));

        $userConfigs = $this->userConfigRepository->search($criteria, $context);
        if ($userConfigs->getTotal() === 0) {
            return;
        }

        $updates = [];

        /** @var UserConfigEntity $userConfig */
        foreach ($userConfigs->getElements() as $userConfig) {
            $updates[] = [
                'id' => $userConfig->getId(),
                'userId' => $userConfig->getUserId(),
                'key' => self::USER_CONFIG_KEY_HIDE_CONSENT_BANNER,
                'value' => ['_value' => false],
            ];
        }

        $this->userConfigRepository->upsert($updates, $context);
    }
}
