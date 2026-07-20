<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Configuration;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\CoversAttributeRule;

/**
 * @internal
 *
 * @extends RuleTestCase<CoversAttributeRule>
 */
class CoversAttributeRuleTest extends RuleTestCase
{
    public function testAllowsConfiguredUnitTestNamespace(): void
    {
        $this->analyse([__DIR__ . '/data/CoversAttributeRule/Unit/ConfiguredUnitFixture.php'], []);
    }

    public function testRejectsCoversAttributeOutsideConfiguredUnitTestNamespace(): void
    {
        $this->analyse([__DIR__ . '/data/CoversAttributeRule/Integration/IntegrationFixture.php'], [
            [
                'Only Unit & Migration test classes can have CoversClass, CoversFunction or CoversNothing attribute',
                8,
            ],
        ]);
    }

    /**
     * @return CoversAttributeRule
     */
    protected function getRule(): Rule
    {
        return new CoversAttributeRule(new Configuration([
            'allowedUnitTestClassNamespaces' => [
                'Shopware\\Tests\\DevOps\\Core\\DevOps\\StaticAnalyse\\PHPStan\\Rules\\data\\CoversAttributeRule\\Unit\\',
            ],
        ]));
    }
}
