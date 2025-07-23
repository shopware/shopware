<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\PublicServiceDecoratorRule;

/**
 * This class represents a decorator that decorates a non-public service and is not marked as public.
 * This should NOT trigger a violation of the PublicServiceDecoratorRule.
 */
class DecoratorOfNonPublicService
{
    // This class is expected to be defined in services.xml as:
    // <service id="..." decorates="some.non.public.service" public="false">
}
