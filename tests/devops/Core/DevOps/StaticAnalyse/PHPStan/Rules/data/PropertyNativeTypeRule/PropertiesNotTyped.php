<?php

declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\PropertyNativeTypeRule;

class PropertiesNotTyped
{
    /**
     * @var string
     */
    public $stringPropertyNotTyped;

    /**
     * @param string $promotedStringPropertyNotTyped
     */
    public function __construct(
        public $promotedStringPropertyNotTyped,
    ) {
    }
}
