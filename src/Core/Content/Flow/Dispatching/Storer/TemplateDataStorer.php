<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Content\Flow\Dispatching\Aware\TemplateDataAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;

#[Package('business-ops')]
class TemplateDataStorer extends FlowStorer
{
    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof TemplateDataAware || isset($stored[TemplateDataAware::TEMPLATE_DATA])) {
            return $stored;
        }

        $stored[TemplateDataAware::TEMPLATE_DATA] = $event->getTemplateData();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(TemplateDataAware::TEMPLATE_DATA)) {
            return;
        }

        $storable->setData(TemplateDataAware::TEMPLATE_DATA, $storable->getStore(TemplateDataAware::TEMPLATE_DATA));
    }
}
