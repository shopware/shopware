<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth\Api\Client;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\ClientTrait;

class AdministrationClient implements ClientEntityInterface
{
    use ClientTrait;

    /**
     * @var string|null
     */
    private $userId;

    /**
     * @var bool
     */
    private $writeAccess;

    public function __construct(?string $userId = null, bool $writeAccess = false)
    {
        $this->userId = $userId;
        $this->writeAccess = $writeAccess;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function hasWriteAccess(): bool
    {
        return $this->writeAccess;
    }

    /**
     * Get the client's identifier.
     *
     * @return string
     */
    public function getIdentifier()
    {
        return 'administration';
    }
}
