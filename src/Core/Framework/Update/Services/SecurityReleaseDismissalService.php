<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Services;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigCollection;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigEntity;

/**
 * @internal
 */
#[Package('framework')]
readonly class SecurityReleaseDismissalService
{
    public const USER_CONFIG_KEY = 'security_release.dismissed';

    /**
     * @param EntityRepository<UserConfigCollection> $userConfigRepository
     */
    public function __construct(
        private EntityRepository $userConfigRepository,
        private ClockInterface $clock,
    ) {
    }

    public function isDismissedByUser(string $userId, string $version, Context $context): bool
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('userId', $userId))
            ->addFilter(new EqualsFilter('key', self::USER_CONFIG_KEY));

        $config = $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($criteria): ?UserConfigEntity {
            return $this->userConfigRepository->search($criteria, $context)->getEntities()->first();
        });

        if ($config === null) {
            return false;
        }

        $value = $config->getValue();

        return ($value['version'] ?? null) === $version;
    }

    public function dismiss(string $userId, string $version, Context $context): void
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('userId', $userId))
            ->addFilter(new EqualsFilter('key', self::USER_CONFIG_KEY));

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($userId, $version, $criteria): void {
            $id = $this->userConfigRepository->searchIds($criteria, $context)->firstId();

            $this->userConfigRepository->upsert([
                [
                    'id' => $id ?? Uuid::randomHex(),
                    'userId' => $userId,
                    'key' => self::USER_CONFIG_KEY,
                    'value' => [
                        'version' => $version,
                        'dismissedAt' => $this->clock->now()->format(\DateTimeInterface::ATOM),
                    ],
                ],
            ], $context);
        });
    }
}
