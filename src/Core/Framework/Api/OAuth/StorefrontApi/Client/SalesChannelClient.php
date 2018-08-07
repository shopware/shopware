<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth\StorefrontApi\Client;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\ClientTrait;

class SalesChannelClient implements ClientEntityInterface
{
    use ClientTrait;

    /**
     * @var string
     */
    private $identifier;

    public function __construct(string $identifier)
    {
        $this->identifier = $identifier;
    }

    /**
     * Get the client's identifier.
     *
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}
