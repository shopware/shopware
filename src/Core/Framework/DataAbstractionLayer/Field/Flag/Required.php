<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

class Required extends Flag
{
    public function parse(): \Generator
    {
        yield 'required' => true;
    }
}
