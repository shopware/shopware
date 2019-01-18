<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountProfile;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\AccountProfile\AccountProfilePageletStruct;
use Shopware\Storefront\Pagelet\Header\HeaderPagelet;

class AccountProfilePageStruct extends Struct
{
    /**
     * @var AccountProfilePageletStruct
     */
    protected $accountProfile;

    /**
     * @var HeaderPagelet
     */
    protected $header;

    /**
     * @return AccountProfilePageletStruct
     */
    public function getAccountProfile(): AccountProfilePageletStruct
    {
        return $this->accountProfile;
    }

    /**
     * @param AccountProfilePageletStruct $accountProfile
     */
    public function setAccountProfile(AccountProfilePageletStruct $accountProfile): void
    {
        $this->accountProfile = $accountProfile;
    }

    public function getHeader(): HeaderPagelet
    {
        return $this->header;
    }

    public function setHeader(HeaderPagelet $header): void
    {
        $this->header = $header;
    }
}
