<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth\User;

use League\OAuth2\Server\Entities\UserEntityInterface;

class User implements UserEntityInterface
{
    /**
     * @var string
     */
    private $userId;

    public function __construct(string $userId)
    {
        $this->userId = $userId;
    }

    /**
     * Return the user's identifier.
     */
    public function getIdentifier()
    {
        return $this->userId;
    }
}
