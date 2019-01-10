<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountOverview;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\AccountProfile\AccountProfilePageletStruct;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletStruct;

class AccountOverviewPageStruct extends Struct
{
    /**
     * @var AccountProfilePageletStruct
     */
    protected $accountProfile;

    /**
     * @var ContentHeaderPageletStruct
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

    public function getHeader(): ContentHeaderPageletStruct
    {
        return $this->header;
    }

    public function setHeader(ContentHeaderPageletStruct $header): void
    {
        $this->header = $header;
    }
}
