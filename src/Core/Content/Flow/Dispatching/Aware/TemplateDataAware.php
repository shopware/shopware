<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Aware;

use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;

#[Package('business-ops')]
interface TemplateDataAware extends FlowEventAware
{
    public const TEMPLATE_DATA = 'templateData';

    /**
     * @return array<string, mixed>
     */
    public function getTemplateData(): array;
}
