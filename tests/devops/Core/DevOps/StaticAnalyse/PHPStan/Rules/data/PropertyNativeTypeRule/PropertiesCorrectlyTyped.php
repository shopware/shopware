<?php

declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\PropertyNativeTypeRule;

class PropertiesCorrectlyTyped
{
    public string $foo;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    public $stringPropertyWithDeprecation;

    /**
     * @var resource
     */
    public $resourceProperty;

    /**
     * @var callable
     */
    public $callableProperty;

    /**
     * @param string $promotedStringPropertyWithDeprecation
     * @param resource $promotedResourceProperty
     * @param callable $promotedCallableProperty
     */
    public function __construct(
        public string $promotedStringProperty,
        /** @deprecated tag:v6.7.0 - Will be natively typed */
        public $promotedStringPropertyWithDeprecation,
        public $promotedResourceProperty,
        public $promotedCallableProperty,
    ) {
    }
}
