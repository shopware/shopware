<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\PublicServiceDecoratorRule;

/**
 * This class represents a decorator that decorates a service but the decorated service does not exist.
 * This should trigger a violation of the PublicServiceDecoratorRule.
 */
class NonExistDecorator
{
    // This class is expected to be defined in services.xml as:
    // <service id="..." decorates="non.exists.service" public="false">
}
