<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation\FuturePropertyCompatibilityRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<FuturePropertyCompatibilityRule>
 */
#[Package('framework')]
class FuturePropertyCompatibilityRuleTest extends RuleTestCase
{
    public function testReportsAssignmentsIncompatibleWithFutureProperties(): void
    {
        $this->analyse([
            __DIR__ . '/data/FuturePropertyCompatibilityRule/FuturePropertyConsumer.php',
            __DIR__ . '/data/FuturePropertyCompatibilityRule/FutureReadonlyPropertyConsumer.php',
        ], [
            [
                'Property "Shopware\\Tests\\DevOps\\Core\\DevOps\\StaticAnalyse\\PHPStan\\Rules\\data\\FuturePropertyCompatibilityRule\\FutureNarrowedProperty::$value" will be narrowed to string in v6.8.0, but null is assigned. Assign string to stay compatible with both versions.',
                9,
            ],
            [
                'Property "Shopware\\Tests\\DevOps\\Core\\DevOps\\StaticAnalyse\\PHPStan\\Rules\\data\\FuturePropertyCompatibilityRule\\FutureReadonlyProperty::$value" will become readonly in v6.8.0. Stop assigning to it outside the declaring class.',
                9,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        return new FuturePropertyCompatibilityRule(
            $this->createReflectionProvider(),
            self::getContainer()->getByType(TypeStringResolver::class),
        );
    }
}
