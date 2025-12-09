<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\Storage;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Consent\ConsentState;
use Shopware\Core\System\Consent\DTO\ConsentStateDTO;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigCollection;

/**
 * @internal
 */
#[Package('data-services')]
class AdminUserStorage implements StorageInterface
{
    private const CONFIG_PREFIX = 'core.consent.';

    /**
     * @param EntityRepository<UserConfigCollection> $userConfigRepository
     */
    public function __construct(
        private readonly EntityRepository $userConfigRepository
    ) {
    }

    public static function code(): string
    {
        return 'admin_user';
    }

    public function status(string $name, string $identifier): ConsentStateDTO
    {
        $configKey = self::CONFIG_PREFIX . $name;

        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('userId', $identifier));
        $criteria->addFilter(new EqualsFilter('key', $configKey));

        $userConfig = $this->userConfigRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        if ($userConfig === null) {
            return new ConsentStateDTO(
                name: $name,
                identifier: $identifier,
                status: ConsentState::REQUESTED
            );
        }

        return new ConsentStateDTO(
            name: $name,
            identifier: $identifier,
            status: ConsentState::from($userConfig->getValue()['_value'] ?? [])
        );
    }

    public function accept(string $name, string $identifier): void
    {
        $configKey = self::CONFIG_PREFIX . $name;

        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('userId', $identifier));
        $criteria->addFilter(new EqualsFilter('key', $configKey));

        $userConfigId = $this->userConfigRepository->searchIds($criteria, Context::createDefaultContext())->firstId();

        $this->userConfigRepository->upsert([
            [
                'id' => $userConfigId ?: Uuid::randomHex(),
                'userId' => $identifier,
                'key' => $configKey,
                'value' => ['_value' => ConsentState::ACCEPTED->value],
            ],
        ], Context::createDefaultContext());
    }

    public function revoke(string $name, string $identifier): void
    {
        $configKey = self::CONFIG_PREFIX . $name;

        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('userId', $identifier));
        $criteria->addFilter(new EqualsFilter('key', $configKey));

        $userConfigId = $this->userConfigRepository->searchIds($criteria, Context::createDefaultContext())->firstId();

        $this->userConfigRepository->upsert([
            [
                'id' => $userConfigId ?: Uuid::randomHex(),
                'userId' => $identifier,
                'key' => $configKey,
                'value' => ['_value' => ConsentState::REVOKED->value],
            ],
        ], Context::createDefaultContext());
    }
}
