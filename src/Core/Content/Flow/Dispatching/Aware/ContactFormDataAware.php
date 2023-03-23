<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Aware;

use Shopware\Core\Content\Flow\Dispatching\Action\FlowMailVariables;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.6.0 - Will be removed, use ScalarValuesStorer/ScalarValuesAware instead
 */
#[Package('business-ops')]
interface ContactFormDataAware extends FlowEventAware
{
    public const CONTACT_FORM_DATA = FlowMailVariables::CONTACT_FORM_DATA;

    /**
     * @return array<string, mixed>
     */
    public function getContactFormData(): array;
}
