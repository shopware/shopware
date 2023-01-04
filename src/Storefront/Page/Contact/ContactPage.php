<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Contact;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Storefront\Page\Page;

/**
 * @deprecated tag:v6.5.0 the according controller was already removed, use store-api ContactRoute instead
 */
#[Package('storefront')]
class ContactPage extends Page
{
    /**
     * @var SalutationCollection
     */
    protected $salutations;

    public function getSalutations(): SalutationCollection
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', 'ContactRoute')
        );

        return $this->salutations;
    }

    public function setSalutations(SalutationCollection $salutations): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', 'ContactRoute')
        );

        $this->salutations = $salutations;
    }
}
