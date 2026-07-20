<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\PublicServiceDecoratorRule;

/**
 * This class represents a decorator that decorates a public service but is not marked as public itself.
 * This should trigger a violation of the PublicServiceDecoratorRule.
 */
class NonPublicDecorator
{
    // This class is expected to be defined in services.xml as:
    // <service id="..." decorates="translator" public="false">
}
