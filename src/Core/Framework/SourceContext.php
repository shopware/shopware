<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use Shopware\Core\Defaults;

class SourceContext
{
    public const ORIGIN_API = 'api';
    public const ORIGIN_STOREFRONT_API = 'storefront';
    public const ORIGIN_SYSTEM = 'system';

    /**
     * @var string
     */
    private $origin;

    /**
     * @var string|null
     */
    private $userId;

    /**
     * @var string|null
     */
    private $integrationId;

    /**
     * @var string
     */
    private $salesChannelId;

    public function __construct(string $origin = self::ORIGIN_SYSTEM)
    {
        $this->origin = $origin;
        $this->salesChannelId = Defaults::SALES_CHANNEL;
    }

    public function getOrigin(): string
    {
        return $this->origin;
    }

    public function setOrigin(string $origin): void
    {
        $this->origin = $origin;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function getIntegrationId(): ?string
    {
        return $this->integrationId;
    }

    public function setIntegrationId(string $integrationId): void
    {
        $this->integrationId = $integrationId;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }
}
