<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class AsArray extends Flag
{
    public function parse(): \Generator
    {
        yield 'as_array' => true;
    }
}
