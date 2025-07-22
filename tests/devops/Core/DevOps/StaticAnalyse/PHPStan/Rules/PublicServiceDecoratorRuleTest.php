<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\PublicServiceDecoratorRule;

/**
 * @internal
 *
 * @extends  RuleTestCase<PublicServiceDecoratorRule>
 */
#[CoversClass(PublicServiceDecoratorRule::class)]
class PublicServiceDecoratorRuleTest extends RuleTestCase
{
    #[RunInSeparateProcess]
    public function testRule(): void
    {
        // Test case where decorator of public service is not public (should trigger error)
        $this->analyse([__DIR__ . '/data/PublicServiceDecoratorRule/NonPublicDecorator.php'], [
            [
                'Service "Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\PublicServiceDecoratorRule\NonPublicDecorator" decorates the public service "translator" but is not marked as public. Decorators of public services must also be public.',
                7,
            ],
        ]);

        // Test case where decorator of public service is public (should not trigger error)
        $this->analyse([__DIR__ . '/data/PublicServiceDecoratorRule/PublicDecorator.php'], []);

        // Test case where decorator of non-public service is not public (should not trigger error)
        $this->analyse([__DIR__ . '/data/PublicServiceDecoratorRule/DecoratorOfNonPublicService.php'], []);
    }

    protected function getRule(): Rule
    {
        return new PublicServiceDecoratorRule(__DIR__ . '/data/PublicServiceDecoratorRule/services.xml');
    }
}