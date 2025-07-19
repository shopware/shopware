<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\AttributeFinalRule;

/**
 * @internal
 *
 * @extends  RuleTestCase<AttributeFinalRule>
 */
#[CoversClass(AttributeFinalRule::class)]
class AttributeFinalRuleTest extends RuleTestCase
{
    public function testFinalAttributeClass(): void
    {
        $this->analyse([
            __DIR__ . '/data/AttributeFinalRule/FinalAttributeClass.php',
        ], []);
    }

    public function testNonFinalAttributeClass(): void
    {
        $this->analyse([
            __DIR__ . '/data/AttributeFinalRule/NonFinalAttributeClass.php',
        ], [[
            'Attribute classes must be declared final.',
            5,
        ]]);
    }

    protected function getRule(): Rule
    {
        return new AttributeFinalRule();
    }
}
