<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal not intended for decoration or replacement
 */
#[Package('business-ops')]
abstract class AbstractFlowLoader
{
    abstract public function load(): array;
}
