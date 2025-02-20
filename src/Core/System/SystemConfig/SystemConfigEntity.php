<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

#[Package('framework')]
class SystemConfigEntity extends Entity
{
    use EntityIdTrait;

    protected string $configurationKey;

    /**
     * @var array<mixed>|bool|float|int|string|null
     */
    protected array|bool|float|int|string|null $configurationValue;

    protected ?string $salesChannelId = null;

    protected ?SalesChannelEntity $salesChannel = null;

    public function getConfigurationKey(): string
    {
        return $this->configurationKey;
    }

    public function setConfigurationKey(string $configurationKey): void
    {
        $this->configurationKey = $configurationKey;
    }

    /**
     * @return array<mixed>|bool|float|int|string|null
     */
    public function getConfigurationValue(): array|bool|float|int|string|null
    {
        return $this->configurationValue;
    }

    /**
     * @param array<mixed>|bool|float|int|string|null $configurationValue
     */
    public function setConfigurationValue(array|bool|float|int|string|null $configurationValue): void
    {
        $this->configurationValue = $configurationValue;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(?string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }
}
