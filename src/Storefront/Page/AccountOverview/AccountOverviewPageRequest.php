<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountOverview;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\AccountProfile\AccountProfilePageletRequest;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletRequest;

class AccountOverviewPageRequest extends Struct
{
    /**
     * @var AccountProfilePageletRequest
     */
    protected $accountProfileRequest;

    /**
     * @var ContentHeaderPageletRequest
     */
    protected $headerRequest;

    public function __construct()
    {
        $this->accountProfileRequest = new AccountProfilePageletRequest();
        $this->headerRequest = new ContentHeaderPageletRequest();
    }

    /**
     * @return AccountProfilePageletRequest
     */
    public function getAccountProfileRequest(): AccountProfilePageletRequest
    {
        return $this->accountProfileRequest;
    }

    public function getHeaderRequest(): ContentHeaderPageletRequest
    {
        return $this->headerRequest;
    }
}
