<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Configuration;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\NoCreateMockWithoutExpectationsRule;
use Shopware\Core\Framework\Log\Package;

// the abstract-base fixtures are not autoloadable (their namespace deliberately sits in the rule's
// enabled unit-test namespaces); loading them lets reflection resolve the subclass -> ancestor walk
require_once __DIR__ . '/data/NoCreateMockWithoutExpectationsRule/AbstractBaseCases.php';

/**
 * @internal
 *
 * @extends RuleTestCase<NoCreateMockWithoutExpectationsRule>
 */
#[Package('framework')]
class NoCreateMockWithoutExpectationsRuleNarrowingTest extends RuleTestCase
{
    public function testEnabledNamespacesSilenceTheOtherTrees(): void
    {
        // the fixtures live under Shopware\Tests\Unit\Core; narrowing enforcement to a commercial
        // namespace must silence them even though they stay inside the supported boundary
        $this->analyse([__DIR__ . '/data/NoCreateMockWithoutExpectationsRule/Cases.php'], []);
    }

    protected function getRule(): Rule
    {
        return new NoCreateMockWithoutExpectationsRule(
            new Configuration([
                'allowedUnitTestClassNamespaces' => ['Shopware\\Tests\\Unit\\', 'Shopware\\Commercial\\Tests\\Unit\\'],
                'createMockWithoutExpectationsEnabledNamespaces' => ['Shopware\\Commercial\\Tests\\Unit\\Sso\\'],
            ]),
            self::getContainer()->getService('defaultAnalysisParser'),
        );
    }
}
