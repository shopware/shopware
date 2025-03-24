<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Event;

use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Contracts\EventDispatcher\Event;

class AbstractStorefrontSnippetsEvent extends Event{
    function __construct(
        public array $snippets,
        public readonly string $locale,
        public readonly MessageCatalogueInterface $catalog,
        public readonly string $snippetSetId,
        public readonly ?string $fallbackLocale = null,
        public readonly ?string $salesChannelId = null
    )
    {   
    }
}
