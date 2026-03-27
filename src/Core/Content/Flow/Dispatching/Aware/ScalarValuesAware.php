<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Aware;

use Shopware\Core\Framework\Event\IsFlowEventAware;
use Shopware\Core\Framework\Log\Package;

#[Package('after-sales')]
#[IsFlowEventAware]
interface ScalarValuesAware
{
    public const STORE_VALUES = 'store_values';

    /**
     * @return array<string, scalar|list<mixed>|null>
     */
    public function getValues(): array;
}
