<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal not intended for decoration or replacement
 * 
 * @phpstan-type TFlows array<string, array<array{id: string, name: string, payload: array<mixed>}>>
 */
#[Package('after-sales')]
abstract class AbstractFlowLoader
{
    /**
     * @return TFlows
     */
    abstract public function load(): array;
}
