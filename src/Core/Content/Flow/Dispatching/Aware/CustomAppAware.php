<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Aware;

use Shopware\Core\Framework\Log\Package;

#[Package('business-ops')]
interface CustomAppAware
{
    public const CUSTOM_DATA = 'customAppData';

    /**
     * @return array<string, mixed>|null
     */
    public function getCustomAppData(): ?array;
}
