<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

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
     * @var null|string
     */
    private $userId;

    /**
     * @var null|string
     */
    private $integrationId;

    /**
     * @var null|string
     */
    private $touchpointId;

    public function __construct(string $origin = self::ORIGIN_SYSTEM)
    {
        $this->origin = $origin;
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

    public function getTouchpointId(): ?string
    {
        return $this->touchpointId;
    }

    public function setTouchpointId(string $touchpointId): void
    {
        $this->touchpointId = $touchpointId;
    }
}
