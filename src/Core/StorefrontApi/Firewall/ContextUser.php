<?php declare(strict_types=1);

namespace Shopware\StorefrontApi\Firewall;

use Shopware\Context\Struct\StorefrontContext;
use Symfony\Component\Security\Core\User\UserInterface;

class ContextUser implements UserInterface
{
    /**
     * @var string
     */
    private $applicationToken;

    /**
     * @var StorefrontContext
     */
    private $context;

    public function __construct(string $applicationToken, StorefrontContext $context)
    {
        $this->applicationToken = $applicationToken;
        $this->context = $context;
    }

    public function getApplicationToken(): string
    {
        return $this->applicationToken;
    }

    public function getContextToken(): string
    {
        return $this->context->getToken();
    }

    public function getContext(): StorefrontContext
    {
        return $this->context;
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
        $roles = ['ROLE_APPLICATION'];

        if ($this->context->getCustomer() !== null) {
            $roles[] = 'ROLE_CUSTOMER';
        }

        return $roles;
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
}
