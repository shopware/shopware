<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Login;

use Shopware\Core\System\Country\CountryCollection;
use Shopware\Storefront\Framework\Page\PageWithHeader;

class AccountLoginPage extends PageWithHeader
{
    /**
     * @var CountryCollection
     */
    protected $countries;

    public function getCountries(): CountryCollection
    {
        return $this->countries;
    }

    public function setCountries(CountryCollection $countries): void
    {
        $this->countries = $countries;
    }
}
