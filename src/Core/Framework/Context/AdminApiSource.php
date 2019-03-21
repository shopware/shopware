<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Context;

class AdminApiSource implements ContextSource
{
    /**
     * @var string|null
     */
    private $userId;

    /**
     * @var string|null
     */
    private $integrationId;

    public function __construct(?string $userId, ?string $integrationId = null)
    {
        $this->userId = $userId;
        $this->integrationId = $integrationId;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function getIntegrationId(): ?string
    {
        return $this->integrationId;
    }
}
