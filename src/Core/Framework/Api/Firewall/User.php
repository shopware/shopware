<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Firewall;

use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface, \JsonSerializable
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $username;

    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var string
     */
    protected $currencyId;

    private function __construct(string $id, string $username, string $languageId, string $currencyId)
    {
        $this->id = $id;
        $this->username = $username;
        $this->languageId = $languageId;
        $this->currencyId = $currencyId;
    }

    public static function createFromDatabase(array $data): self
    {
        return new self(
            Uuid::fromBytesToHex($data['id']),
            $data['username'],
            Uuid::fromBytesToHex($data['languageId']),
            Uuid::fromBytesToHex($data['currencyId'])
        );
    }

    /**
     * Returns the roles granted to the user.
     *
     * <code>
     * public function getRoles()
     * {
     *     return array('ROLE_USER');
     * }
     * </code>
     *
     * Alternatively, the roles might be stored on a ``roles`` property,
     * and populated in any number of different ways when the user object
     * is created.
     *
     * @return (Role|string)[] The user roles
     */
    public function getRoles()
    {
        return ['ROLE_ADMIN'];
    }

    /**
     * Returns the password used to authenticate the user.
     *
     * This should be the encoded password. On authentication, a plain-text
     * password will be salted, encoded, and then compared to this value.
     *
     * @return string The password
     */
    public function getPassword()
    {
        return '';
    }

    /**
     * Returns the salt that was originally used to encode the password.
     *
     * This can return null if the password was not encoded using a salt.
     *
     * @return string|null The salt
     */
    public function getSalt()
    {
        return null;
    }

    /**
     * Returns the username used to authenticate the user.
     *
     * @return string The username
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Removes sensitive data from the user.
     *
     * This is important if, at any given point, sensitive information like
     * the plain-text password is stored on this object.
     */
    public function eraseCredentials()
    {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function getCurrencyId(): string
    {
        return $this->currencyId;
    }

    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
}
