<?php

declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Validation;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\System\SalesChannel\SalesChannelException;

/**
 * @internal
 *
 * @extends Collection<SalesChannelData>
 */
#[Package('discovery')]
class Mapping extends Collection
{
    /**
     * @param iterable<string, SalesChannelData> $elements indexed by sales channel ID
     */
    public function __construct(iterable $elements = [])
    {
        parent::__construct($elements);
    }

    public function add($element): void
    {
        throw SalesChannelException::invalidMappingOperation('SalesChannelData needs to be added indexed by sales channel ID. Use set() instead.');
    }

    /**
     * @param string $key sales channel ID
     * @param SalesChannelData $element
     */
    public function set($key, $element): void
    {
        parent::set($key, $element);
    }

    protected function getExpectedClass(): string
    {
        return SalesChannelData::class;
    }
}
