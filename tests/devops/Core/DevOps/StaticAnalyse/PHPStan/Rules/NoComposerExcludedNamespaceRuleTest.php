<?php

declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\NoComposerExcludedNamespaceRule;

/**
 * @internal
 *
 * @extends  RuleTestCase<NoComposerExcludedNamespaceRule>
 */
#[CoversClass(NoComposerExcludedNamespaceRule::class)]
class NoComposerExcludedNamespaceRuleTest extends RuleTestCase
{
    public function testProductionCodeUsingTestNamespaceFails(): void
    {
        $this->analyse(
            [__DIR__ . '/data/NoComposerExcludedNamespace/src/Example/ProductionCodeUsingTestClass.php'],[
            [
                'Importing Shopware\Core\Test\TestDefaults from excluded test namespace is forbidden.',
                5,
            ],
        ]);
    }

    public function testProductionCodeWithValidImportPasses(): void
    {
        $this->analyse([__DIR__ . '/data/NoComposerExcludedNamespace/src/Example/ProductionCodeValid.php'], []);
    }

    protected function getRule(): Rule
    {
        return new NoComposerExcludedNamespaceRule();
    }
}
