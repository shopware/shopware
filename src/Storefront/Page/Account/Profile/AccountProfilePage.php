<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Profile;

use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Storefront\Page\Page;

class AccountProfilePage extends Page
{
    /**
     * @var SalutationCollection
     */
    protected $salutations;

    public function getSalutations(): SalutationCollection
    {
        return $this->salutations;
    }

    public function setSalutations(SalutationCollection $salutations): void
    {
        $this->salutations = $salutations;
    }
}
