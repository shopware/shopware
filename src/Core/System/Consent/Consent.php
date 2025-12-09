<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Consent\DTO\ConsentStateDTO;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigEntity;

interface ConsentPrototype
{
    public function status(string $name, string $identifier);

    public function accept(string $name, string $identifier);

    public function revoke(string $name, string $identifier);

    public function code(): string;
}

class AdminUserConsent implements ConsentPrototype
{
    private const CONFIG_PREFIX = 'core.consent.';

    /**
     * @param EntityRepository<UserConfigEntity> $userConfigRepository
     */
    public function __construct(
        private readonly EntityRepository $userConfigRepository
    )
    { }

    public function code(): string {
        return 'admin_user_consent';
    }

    public function status(string $name, string $identifier)
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
            status: ConsentState::from($userConfig->getValue()['_value'])
        );
    }

    public function accept(string $name, string $identifier)
    {
        $configKey = self::CONFIG_PREFIX . $name;

        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('userId', $identifier));
        $criteria->addFilter(new EqualsFilter('key', $configKey));

        $userConfigId = $this->userConfigRepository->searchIds($criteria, Context::createDefaultContext())->firstId();;

        $this->userConfigRepository->upsert([
            [
                'id' => $userConfigId ?: Uuid::randomHex(),
                'userId' => $identifier,
                'key' => $configKey,
                'value' => ['_value' => ConsentState::ACCEPTED->value],
            ],
        ], Context::createDefaultContext());
    }

    public function revoke(string $name, string $identifier)
    {
        $configKey = self::CONFIG_PREFIX . $name;

        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('userId', $identifier));
        $criteria->addFilter(new EqualsFilter('key', $configKey));

        $userConfigId = $this->userConfigRepository->searchIds($criteria, Context::createDefaultContext())->firstId();;

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

class SystemConfigConsent implements ConsentPrototype
{
    private const SYSTEM_CONFIG_PREFIX = 'core.consent.';

    public function __construct(private readonly SystemConfigService $service)
    { }

    public function code(): string {
        return 'system_config_consent';
    }

    public function status(string $name, string $identifier): ConsentStateDTO
    {
        $configEntry = $this->service->get(self::SYSTEM_CONFIG_PREFIX . $name);

        return new ConsentStateDTO(
            name: $name,
            identifier: $identifier,
            status: ConsentState::from($configEntry['status'])
        );
    }

    public function accept(string $name, string $identifier)
    {
        $this->service->set(self::SYSTEM_CONFIG_PREFIX . $name, [
            'user' => $identifier,
            'status' => ConsentState::ACCEPTED->value,
            'timestamp' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    public function revoke(string $name, string $identifier)
    {
        $this->service->set(self::SYSTEM_CONFIG_PREFIX . $name, [
            'user' => $identifier,
            'status' => ConsentState::REVOKED->value,
            'timestamp' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}