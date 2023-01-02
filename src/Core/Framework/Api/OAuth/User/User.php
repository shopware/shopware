<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth\User;

use Shopware\Core\Framework\Log\Package;
use League\OAuth2\Server\Entities\UserEntityInterface;

/**
 * @package core
 */
#[Package('core')]
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
     *
     * @return string
     */
    public function getIdentifier()
    {
        return $this->userId;
    }
}
