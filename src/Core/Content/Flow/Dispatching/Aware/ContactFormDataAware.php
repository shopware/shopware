<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Aware;

use Shopware\Core\Framework\Event\FlowEventAware;

interface ContactFormDataAware extends FlowEventAware
{
    public const CONTACT_FORM_DATA = 'contactFormData';

    /**
     * @return array<string, mixed>
     */
    public function getContactFormData(): array;
}
