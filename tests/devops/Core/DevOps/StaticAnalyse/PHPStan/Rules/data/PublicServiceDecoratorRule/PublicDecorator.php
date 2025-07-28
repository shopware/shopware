<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\PublicServiceDecoratorRule;

/**
 * This class represents a decorator that decorates a public service and is marked as public.
 * This should NOT trigger a violation of the PublicServiceDecoratorRule.
 */
class PublicDecorator
{
    // This class is expected to be defined in services.xml as:
    // <service id="..." decorates="translator" public="true">
}
