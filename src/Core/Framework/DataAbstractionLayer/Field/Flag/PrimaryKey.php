<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class PrimaryKey extends Flag
{
    /**
     * @param non-empty-string|null $iterationIndex Name of a covering secondary index for ID-only iteration
     */
    public function __construct(public readonly ?string $iterationIndex = null)
    {
    }

    public function parse(): \Generator
    {
        yield 'primary_key' => true;
    }
}
