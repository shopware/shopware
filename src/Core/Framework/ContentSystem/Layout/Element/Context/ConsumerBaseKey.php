<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Context;

use Shopware\Core\Framework\Log\Package;

/**
 * The property key a consumer writes reduced to the write boundary's uniqueness axis: the key's first dotted segment, or the whole key when it carries no dot.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ConsumerBaseKey
{
    public function of(string $key): string
    {
        return str_contains($key, '.') ? substr($key, 0, (int) strpos($key, '.')) : $key;
    }
}
