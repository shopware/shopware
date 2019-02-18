<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth\Client;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\ClientTrait;

class ApiClient implements ClientEntityInterface
{
    use ClientTrait;

    /**
     * @var bool
     */
    private $writeAccess;

    /**
     * @var string
     */
    private $identifier;

    public function __construct(string $identifier, bool $writeAccess, string $name = '')
    {
        $this->writeAccess = $writeAccess;
        $this->identifier = $identifier;
        $this->name = $name;
    }

    public function getWriteAccess(): bool
    {
        return $this->writeAccess;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}
