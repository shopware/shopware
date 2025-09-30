<?php

declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\PropertyNativeTypeRule;

class PropertiesCorrectlyTyped
{
    public string $foo;

    /**
     * @var resource
     */
    public $resourceProperty;

    /**
     * @var callable
     */
    public $callableProperty;

    /**
     * @param resource $promotedResourceProperty
     * @param callable $promotedCallableProperty
     */
    public function __construct(
        public string $promotedStringProperty,
        public $promotedResourceProperty,
        public $promotedCallableProperty,
    ) {
    }
}
