<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Firewall;

use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Component\Security\Core\User\UserInterface;

class Touchpoint implements UserInterface
{
    /**
     * @var string
     */
    private $touchpointId;

    /**
     * @var string
     */
    private $languageId;

    /**
     * @var string
     */
    private $currencyId;

    /**
     * @var string
     */
    private $paymentMethodId;

    /**
     * @var string
     */
    private $shippingMethodId;

    /**
     * @var string
     */
    private $countryId;

    /**
     * @var string
     */
    private $taxCalculationType;

    /**
     * @var string[]
     */
    private $catalogIds;

    /**
     * @var string[]
     */
    private $languageIds;

    private function __construct(string $touchpointId, string $languageId, string $currencyId, string $paymentMethodId, string $shippingMethodId, string $countryId, string $taxCalculationType, array $catalogIds, array $languageIds)
    {
        $this->touchpointId = $touchpointId;
        $this->languageId = $languageId;
        $this->currencyId = $currencyId;
        $this->paymentMethodId = $paymentMethodId;
        $this->shippingMethodId = $shippingMethodId;
        $this->countryId = $countryId;
        $this->taxCalculationType = $taxCalculationType;
        $this->catalogIds = $catalogIds;
        $this->languageIds = $languageIds;
    }

    public static function createFromDatabase(array $data): self
    {
        return new self(
            Uuid::fromBytesToHex($data['id']),
            Uuid::fromBytesToHex($data['language_id']),
            Uuid::fromBytesToHex($data['currency_id']),
            Uuid::fromBytesToHex($data['payment_method_id']),
            Uuid::fromBytesToHex($data['shipping_method_id']),
            Uuid::fromBytesToHex($data['country_id']),
            $data['tax_calculation_type'],
            json_decode($data['catalog_ids'], true),
            json_decode($data['language_ids'], true)
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
     * @return string[]
     */
    public function getRoles()
    {
        return ['ROLE_TOUCHPOINT'];
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
        return $this->touchpointId;
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

    public function getTouchpointId(): string
    {
        return $this->touchpointId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function getCurrencyId(): string
    {
        return $this->currencyId;
    }

    public function getPaymentMethodId(): string
    {
        return $this->paymentMethodId;
    }

    public function getShippingMethodId(): string
    {
        return $this->shippingMethodId;
    }

    public function getCountryId(): string
    {
        return $this->countryId;
    }

    public function getTaxCalculationType(): string
    {
        return $this->taxCalculationType;
    }

    public function getCatalogIds(): array
    {
        return $this->catalogIds;
    }

    public function getLanguageIds(): array
    {
        return $this->languageIds;
    }
}
