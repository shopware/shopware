<?php declare(strict_types=1);

namespace Shopware\RoaveBackwardCompatibility\SimpleAnnotation;

class ParseConfig
{
    public array $numericArgumentMapping;

    public function __construct(array $numericArgumentMapping = [])
    {
        $this->numericArgumentMapping = $numericArgumentMapping;
    }
}
