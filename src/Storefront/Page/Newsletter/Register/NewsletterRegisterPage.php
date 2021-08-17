<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Newsletter\Register;

use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Storefront\Page\Page;

/**
 * @deprecated tag:v6.5.0 - Will be removed
 */
class NewsletterRegisterPage extends Page
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
