<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class ReverseInherited extends Flag
{
    public function __construct(protected string $propertyName)
    {
    }

    public function getReversedPropertyName(): string
    {
        return $this->propertyName;
    }

    public function parse(): \Generator
    {
        yield 'reversed_inherited' => $this->propertyName;
    }
}
