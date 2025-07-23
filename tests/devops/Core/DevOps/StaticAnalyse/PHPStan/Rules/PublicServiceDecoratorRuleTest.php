<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Symfony\XmlServiceMapFactory;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\PublicServiceDecoratorRule;

/**
 * @internal
 *
 * @extends  RuleTestCase<PublicServiceDecoratorRule>
 */
#[CoversClass(PublicServiceDecoratorRule::class)]
class PublicServiceDecoratorRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        // Test case where decorator of public service is not public (should trigger error)
        $this->analyse([__DIR__ . '/data/PublicServiceDecoratorRule/NonPublicDecorator.php'], [
            [
                'Service "Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\PublicServiceDecoratorRule\NonPublicDecorator" decorates the public service "translator" but is not marked as public. Decorators of public services must also be public.',
                9,
            ],
        ]);

        // Test case where decorator of public service is public (should not trigger error)
        $this->analyse([__DIR__ . '/data/PublicServiceDecoratorRule/PublicDecorator.php'], []);

        // Test case where decorator of non-public service is not public (should not trigger error)
        $this->analyse([__DIR__ . '/data/PublicServiceDecoratorRule/DecoratorOfNonPublicService.php'], []);

        // Test case where decorator of non-exist service (should trigger error)
        $this->analyse([__DIR__ . '/data/PublicServiceDecoratorRule/NonExistDecorator.php'], [
            [
                'Service "Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\PublicServiceDecoratorRule\NonExistDecorator" is a decorator for "some.non.exists.service", but the decorated service does not exist.',
                9,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        /** @phpstan-ignore phpstanApi.constructor */
        $factory = new XmlServiceMapFactory(
            __DIR__ . '/data/PublicServiceDecoratorRule/services.xml'
        );

        /** @phpstan-ignore phpstanApi.method */
        return new PublicServiceDecoratorRule($factory->create());
    }
}
