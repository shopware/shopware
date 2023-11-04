<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Aware;

use Shopware\Core\Framework\Log\Package;

#[Package('business-ops')]
interface ScalarValuesAware
{
    public const STORE_VALUES = 'store_values';

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array;
}
