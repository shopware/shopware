<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class ReverseInherited extends Flag
{
    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $propertyName;

    public function __construct(string $propertyName)
    {
        $this->propertyName = $propertyName;
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
