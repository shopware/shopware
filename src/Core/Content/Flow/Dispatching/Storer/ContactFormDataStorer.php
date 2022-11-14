<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Content\Flow\Dispatching\Aware\ContactFormDataAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Event\FlowEventAware;

/**
 * @package business-ops
 */
class ContactFormDataStorer extends FlowStorer
{
    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof ContactFormDataAware || isset($stored[ContactFormDataAware::CONTACT_FORM_DATA])) {
            return $stored;
        }

        $stored[ContactFormDataAware::CONTACT_FORM_DATA] = $event->getContactFormData();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(ContactFormDataAware::CONTACT_FORM_DATA)) {
            return;
        }

        $storable->setData(ContactFormDataAware::CONTACT_FORM_DATA, $storable->getStore(ContactFormDataAware::CONTACT_FORM_DATA));
    }
}
