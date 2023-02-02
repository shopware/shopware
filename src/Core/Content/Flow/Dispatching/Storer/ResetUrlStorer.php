<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Content\Flow\Dispatching\Aware\ResetUrlAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;

#[Package('business-ops')]
class ResetUrlStorer extends FlowStorer
{
    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof ResetUrlAware || isset($stored[ResetUrlAware::RESET_URL])) {
            return $stored;
        }

        $stored[ResetUrlAware::RESET_URL] = $event->getResetUrl();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(ResetUrlAware::RESET_URL)) {
            return;
        }

        $storable->setData(ResetUrlAware::RESET_URL, $storable->getStore(ResetUrlAware::RESET_URL));
    }
}
