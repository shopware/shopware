<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Struct\Struct;

class LicenseDomainStruct extends Struct
{
    /**
     * @var string
     */
    protected $domain;

    /**
     * @var bool
     */
    protected $verified = false;

    /**
     * @var string
     */
    protected $edition = 'Community Edition';

    /**
     * @var bool
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
}
