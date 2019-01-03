<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountProfile;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\AccountProfile\AccountProfilePageletRequest;
use Shopware\Storefront\Pagelet\Header\HeaderPageletRequestTrait;

class AccountProfilePageRequest extends Struct
{
    use HeaderPageletRequestTrait;

    /**
     * @var AccountProfilePageletRequest
     */
    protected $accountProfileRequest;

    /**
     * @return AccountProfilePageletRequest
     */
    public function getAccountProfileRequest(): AccountProfilePageletRequest
    {
        return $this->accountProfileRequest;
    }

    /**
     * @param AccountProfilePageletRequest $accountProfileRequest
     */
    public function setAccountProfileRequest(AccountProfilePageletRequest $accountProfileRequest): void
    {
        $this->accountProfileRequest = $accountProfileRequest;
    }
}
