<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class LicenseDomainStruct extends Struct
{
    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $domain;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $verified = false;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $edition = 'Community Edition';

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $active = false;

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function getEdition(): string
    {
        return $this->edition;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getApiAlias(): string
    {
        return 'store_license_domain';
    }
}
