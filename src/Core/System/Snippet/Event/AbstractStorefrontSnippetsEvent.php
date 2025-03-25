<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Event;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('discovery')]
class AbstractStorefrontSnippetsEvent extends Event
{
    public function __construct(
        public array $snippets,
        public readonly string $locale,
        public readonly MessageCatalogueInterface $catalog,
        public readonly string $snippetSetId,
        public readonly ?string $fallbackLocale = null,
        public readonly ?string $salesChannelId = null
    ) {
    }
}
